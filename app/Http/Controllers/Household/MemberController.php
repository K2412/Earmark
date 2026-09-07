<?php

namespace App\Http\Controllers\Household;

use App\Actions\Households\CreateInvitation;
use App\Concerns\ResolvesHousehold;
use App\Enums\HouseholdRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\CancelInvitationRequest;
use App\Http\Requests\Household\StoreInvitationRequest;
use App\Models\HouseholdInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    use ResolvesHousehold;

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $members = $household->members()
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $role = $member->pivot->role;

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $role instanceof HouseholdRole ? $role->label() : HouseholdRole::from((string) $role)->label(),
                ];
            });

        $invitations = $household->invitations()
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (HouseholdInvitation $invitation) => [
                'id' => $invitation->id,
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->label(),
                'expires' => $invitation->expires_at?->diffForHumans() ?? __('never'),
                'url' => url('/register?invite='.$invitation->code),
            ]);

        return Inertia::render('household/Members', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => HouseholdRole::assignable(),
            'inviteUrl' => $request->session()->get('inviteUrl'),
            'canInvite' => $request->user()->can('inviteMember', $household),
            'canCancel' => $request->user()->can('cancelInvitation', $household),
        ]);
    }

    public function store(StoreInvitationRequest $request, CreateInvitation $action): RedirectResponse
    {
        $invitation = $action->handle($request->validated(), $this->household($request), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invite link generated.')]);

        return to_route('household.members.index')->with('inviteUrl', url('/register?invite='.$invitation->code));
    }

    public function destroyInvitation(CancelInvitationRequest $request, HouseholdInvitation $invitation): RedirectResponse
    {
        $household = $this->household($request);

        abort_unless($invitation->household_id === $household->id, 403);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('household.members.index');
    }
}
