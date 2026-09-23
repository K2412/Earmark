<?php

namespace App\Http\Controllers\Household;

use App\Actions\Reconciliation\ReconcileAccount;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\ReconcileAccountRequest;
use App\Models\Account;
use App\Models\Reconciliation;
use App\Services\Reconciliation\ReconciliationService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private ReconciliationService $reconciliation) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $history = Reconciliation::query()
            ->where('household_id', $household->id)
            ->with('account:id,name')
            ->orderByDesc('reconciled_at')
            ->limit(50)
            ->get()
            ->map(fn (Reconciliation $reconciliation): array => [
                'id' => $reconciliation->id,
                'account' => $reconciliation->account?->name,
                'statement_date' => $reconciliation->statement_date->toDateString(),
                'statement_balance' => Money::format($reconciliation->statement_balance),
                'status' => $reconciliation->status,
            ]);

        return Inertia::render('household/Reconcile', [
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'history' => $history,
            'defaults' => ['statement_date' => now()->toDateString()],
        ]);
    }

    public function preview(ReconcileAccountRequest $request): JsonResponse
    {
        $household = $this->household($request);
        $data = $request->validated();
        $account = Account::query()->where('household_id', $household->id)->findOrFail($data['account_id']);

        $calculated = $this->reconciliation->calculatedBalance($account, $data['statement_date']);
        $discrepancy = $data['statement_balance'] - $calculated;

        return response()->json([
            'calculated' => $calculated,
            'calculated_formatted' => Money::format($calculated),
            'discrepancy' => $discrepancy,
            'discrepancy_formatted' => Money::format($discrepancy),
            'matched' => $discrepancy === 0,
        ]);
    }

    public function store(ReconcileAccountRequest $request, ReconcileAccount $action): RedirectResponse
    {
        $household = $this->household($request);
        $data = $request->validated();
        $account = Account::query()->where('household_id', $household->id)->findOrFail($data['account_id']);

        $result = $action->handle($account, $data['statement_date'], $data['statement_balance'], $request->user());

        if ($result['matched']) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reconciled — balances match.')]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Not reconciled: off by :amount. Clear or correct rows until it matches.', [
                    'amount' => Money::format($result['discrepancy']),
                ]),
            ]);
        }

        return to_route('household.reconcile.index');
    }
}
