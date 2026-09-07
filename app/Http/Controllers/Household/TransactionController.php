<?php

namespace App\Http\Controllers\Household;

use App\Actions\Transactions\CreateTransaction;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreTransactionRequest;
use App\Http\Requests\Household\SuggestPayeeRequest;
use App\Services\Payee\PayeeRuleService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private PayeeRuleService $payeeRules) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $transactions = $household->transactions()
            ->with(['account', 'category', 'bucket'])
            ->whereNull('transfer_pair_id')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'account' => $transaction->account?->name,
                'payee' => $transaction->payee,
                'category' => $transaction->category?->name ?? ($transaction->is_split ? __('(split)') : '—'),
                'bucket' => $transaction->bucket?->name ?? ($transaction->is_split ? __('(split)') : '—'),
                'amount' => Money::format($transaction->amount),
            ]);

        return Inertia::render('household/Transactions', [
            'transactions' => $transactions,
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'categories' => $household->categories()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'buckets' => $household->buckets()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'defaults' => [
                'date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request, CreateTransaction $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction created.')]);

        return to_route('household.transactions.index');
    }

    public function suggest(SuggestPayeeRequest $request): JsonResponse
    {
        $match = $this->payeeRules->suggest($request->validated('payee'), $this->household($request));

        return response()->json([
            'rule_id' => $match['rule']?->id,
            'category_id' => $match['category_id'],
            'bucket_id' => $match['bucket_id'],
        ]);
    }
}
