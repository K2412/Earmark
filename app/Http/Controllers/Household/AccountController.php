<?php

namespace App\Http\Controllers\Household;

use App\Actions\Accounts\CreateAccount;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreAccountRequest;
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

        $accounts = $household->accounts()
            ->where('archived', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => str_replace('_', ' ', $account->type),
                'starting_balance' => Money::format($account->starting_balance),
                'starting_balance_date' => $account->starting_balance_date->toDateString(),
            ]);

        return Inertia::render('household/Accounts', [
            'accounts' => $accounts,
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
}
