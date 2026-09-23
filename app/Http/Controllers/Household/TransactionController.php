<?php

namespace App\Http\Controllers\Household;

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\DeleteTransaction;
use App\Actions\Transactions\MarkTransactionsReviewed;
use App\Actions\Transactions\UpdateTransaction;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\BulkReviewRequest;
use App\Http\Requests\Household\StoreTransactionRequest;
use App\Http\Requests\Household\SuggestPayeeRequest;
use App\Http\Requests\Household\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Models\TransactionActivity;
use App\Services\Payee\PayeeRuleService;
use App\Services\Transaction\TransactionRegisterService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use ResolvesHousehold;

    public function __construct(
        private PayeeRuleService $payeeRules,
        private TransactionRegisterService $register,
    ) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $filters = [
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
            'account_id' => $request->string('account_id')->toString() ?: null,
            'category_id' => $request->string('category_id')->toString() ?: null,
            'bucket_id' => $request->string('bucket_id')->toString() ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'payee' => $request->string('payee')->toString() ?: null,
            'amount_min' => $request->filled('amount_min') ? (int) $request->input('amount_min') : null,
            'amount_max' => $request->filled('amount_max') ? (int) $request->input('amount_max') : null,
            'cleared' => $request->filled('cleared') ? $request->boolean('cleared') : null,
            'reviewed' => $request->filled('reviewed') ? $request->boolean('reviewed') : null,
        ];

        $transactions = $this->register->query($household, $filters)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Transaction $transaction): array => [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'account_id' => $transaction->account_id,
                'account' => $transaction->account?->name,
                'payee' => $transaction->payee,
                'category_id' => $transaction->category_id,
                'category' => $transaction->category?->name ?? ($transaction->is_split ? __('(split)') : null),
                'bucket_id' => $transaction->bucket_id,
                'bucket' => $transaction->bucket?->name ?? ($transaction->is_split ? __('(split)') : null),
                'amount' => $transaction->amount,
                'amount_formatted' => Money::format($transaction->amount),
                'memo' => $transaction->memo,
                'cleared' => $transaction->cleared,
                'reviewed' => $transaction->reviewed,
                'source' => $transaction->source,
                'is_split' => $transaction->is_split,
            ]);

        $activities = TransactionActivity::query()
            ->where('household_id', $household->id)
            ->with('user:id,name')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (TransactionActivity $activity): array => [
                'id' => $activity->id,
                'action' => $activity->action,
                'description' => $activity->description,
                'user' => $activity->user?->name,
                'at' => $activity->created_at?->diffForHumans(),
            ]);

        return Inertia::render('household/Transactions', [
            'transactions' => $transactions,
            'filters' => $filters,
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'categories' => $household->categories()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'buckets' => $household->buckets()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'sources' => ['manual', 'imported_csv', 'imported_pdf'],
            'activities' => $activities,
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

    public function update(UpdateTransactionRequest $request, Transaction $transaction, UpdateTransaction $action): RedirectResponse
    {
        $action->handle($transaction, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction updated.')]);

        return back();
    }

    public function destroy(Request $request, Transaction $transaction, DeleteTransaction $action): RedirectResponse
    {
        $household = $this->household($request);

        abort_unless($transaction->household_id === $household->id, 404);
        abort_if($transaction->transfer_pair_id !== null, 422, 'Transfers are managed on the transfers page.');
        abort_if($transaction->reconciled, 422, 'Reconciled transactions cannot be deleted.');

        $action->handle($transaction, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transaction deleted.')]);

        return back();
    }

    public function review(BulkReviewRequest $request, MarkTransactionsReviewed $action): RedirectResponse
    {
        $action->handle(
            $request->validated('ids'),
            $request->boolean('reviewed'),
            $request->user(),
            $this->household($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review status updated.')]);

        return back();
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
