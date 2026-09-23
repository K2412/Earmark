<?php

namespace App\Http\Controllers\Household;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\ReorderAccounts;
use App\Actions\Accounts\SetAccountArchived;
use App\Actions\Accounts\UpdateAccount;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\ReorderAccountsRequest;
use App\Http\Requests\Household\StoreAccountRequest;
use App\Http\Requests\Household\UpdateAccountRequest;
use App\Models\Account;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    use ResolvesHousehold;

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $shape = fn (Account $account): array => [
            'id' => $account->id,
            'name' => $account->name,
            'institution' => $account->institution,
            'type' => $account->type,
            'type_label' => Account::TYPES[$account->type] ?? $account->type,
            'currency' => $account->currency,
            'owner_user_id' => $account->owner_user_id,
            'owner' => $account->owner?->name,
            'starting_balance' => $account->starting_balance,
            'starting_balance_formatted' => Money::format($account->starting_balance),
            'starting_balance_date' => $account->starting_balance_date->toDateString(),
            'archived' => $account->archived,
        ];

        $accounts = $household->accounts()->with('owner:id,name')
            ->where('archived', false)
            ->orderBy('sort_order')->orderBy('name')
            ->get()->map($shape);

        $archived = $household->accounts()->with('owner:id,name')
            ->where('archived', true)
            ->orderBy('name')
            ->get()->map($shape);

        return Inertia::render('household/Accounts', [
            'accounts' => $accounts,
            'archivedAccounts' => $archived,
            'members' => $household->members()->get(['users.id', 'users.name'])
                ->map(fn ($member): array => ['id' => $member->id, 'name' => $member->name]),
            'types' => collect(Account::TYPES)->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'defaults' => [
                'starting_balance_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreAccountRequest $request, CreateAccount $action): RedirectResponse
    {
        $action->handle($request->validated(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created.')]);

        return to_route('household.accounts.index');
    }

    public function update(UpdateAccountRequest $request, Account $account, UpdateAccount $action): RedirectResponse
    {
        $action->handle($account, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return back();
    }

    public function archive(Request $request, Account $account, SetAccountArchived $action): RedirectResponse
    {
        $this->authorizeAccount($request, $account);
        $action->handle($account, true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account archived.')]);

        return back();
    }

    public function restore(Request $request, Account $account, SetAccountArchived $action): RedirectResponse
    {
        $this->authorizeAccount($request, $account);
        $action->handle($account, false);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account restored.')]);

        return back();
    }

    public function reorder(ReorderAccountsRequest $request, ReorderAccounts $action): RedirectResponse
    {
        $action->handle($this->household($request), $request->validated('ids'));

        return back();
    }

    private function authorizeAccount(Request $request, Account $account): void
    {
        abort_unless($account->household_id === $this->household($request)->id, 404);
    }
}
