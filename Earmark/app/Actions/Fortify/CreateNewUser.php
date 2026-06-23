<?php

namespace App\Actions\Fortify;

use App\Actions\Households\CreateHousehold;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\HouseholdInvitation;
use App\Models\HouseholdMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateHousehold $createHousehold)
    {
        //
    }

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $pendingInviteCode = session()->pull('pending_invite');
            $invitation = $pendingInviteCode
                ? HouseholdInvitation::query()
                    ->where('code', $pendingInviteCode)
                    ->whereNull('accepted_at')
                    ->first()
                : null;

            if ($invitation) {
                // Invited user joins the inviting household with the invitation's role.
                HouseholdMembership::create([
                    'household_id' => $invitation->household_id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);
                $invitation->update(['accepted_at' => now()]);
            } else {
                // Fallback: create a fresh household for the user (mostly for tests
                // that bypass the invite flow).
                $this->createHousehold->handle($user, "{$input['name']}'s Household");
            }

            return $user;
        });
    }
}
