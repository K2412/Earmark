<?php

namespace App\Http\Controllers\Household;

use App\Actions\Rules\ApplyPayeeRule;
use App\Actions\Rules\CreatePayeeRule;
use App\Actions\Rules\DeletePayeeRule;
use App\Actions\Rules\ReorderPayeeRules;
use App\Actions\Rules\UpdatePayeeRule;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\PreviewPayeeRuleRequest;
use App\Http\Requests\Household\ReorderPayeeRulesRequest;
use App\Http\Requests\Household\StorePayeeRuleRequest;
use App\Http\Requests\Household\UpdatePayeeRuleRequest;
use App\Models\PayeeRule;
use App\Services\Payee\PayeeRuleService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayeeRuleController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private PayeeRuleService $rules) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $rules = PayeeRule::query()
            ->where('household_id', $household->id)
            ->with(['category:id,name', 'bucket:id,name'])
            ->orderBy('priority')->orderBy('created_at')
            ->get()
            ->map(fn (PayeeRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'label' => $rule->label(),
                'pattern' => $rule->pattern,
                'enabled' => $rule->enabled,
                'category_id' => $rule->category_id,
                'category' => $rule->category?->name,
                'bucket_id' => $rule->bucket_id,
                'bucket' => $rule->bucket?->name,
                'rename_to' => $rule->rename_to,
                'hide_from_reports' => $rule->hide_from_reports,
                'mark_for_review' => $rule->mark_for_review,
                'auto_apply' => $rule->auto_apply,
            ]);

        return Inertia::render('household/Rules', [
            'rules' => $rules,
            'categories' => $household->categories()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'buckets' => $household->buckets()->where('archived', false)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePayeeRuleRequest $request, CreatePayeeRule $action): RedirectResponse
    {
        $action->handle($request->validated(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule created.')]);

        return to_route('household.rules.index');
    }

    public function update(UpdatePayeeRuleRequest $request, PayeeRule $payeeRule, UpdatePayeeRule $action): RedirectResponse
    {
        $action->handle($payeeRule, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule updated.')]);

        return back();
    }

    public function destroy(Request $request, PayeeRule $payeeRule, DeletePayeeRule $action): RedirectResponse
    {
        abort_unless($payeeRule->household_id === $this->household($request)->id, 404);

        $action->handle($payeeRule);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rule deleted.')]);

        return back();
    }

    public function reorder(ReorderPayeeRulesRequest $request, ReorderPayeeRules $action): RedirectResponse
    {
        $action->handle($this->household($request), $request->validated('ids'));

        return back();
    }

    public function apply(Request $request, PayeeRule $payeeRule, ApplyPayeeRule $action): RedirectResponse
    {
        abort_unless($payeeRule->household_id === $this->household($request)->id, 404);

        $count = $action->handle($payeeRule, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Applied to :count transaction(s).', ['count' => $count])]);

        return back();
    }

    public function preview(PreviewPayeeRuleRequest $request): JsonResponse
    {
        $household = $this->household($request);
        $data = $request->validated();

        $draft = new PayeeRule([
            'pattern' => $data['pattern'],
            'category_id' => $data['category_id'] ?? null,
            'bucket_id' => $data['bucket_id'] ?? null,
            'rename_to' => $data['rename_to'] ?? null,
            'hide_from_reports' => (bool) ($data['hide_from_reports'] ?? false),
            'mark_for_review' => (bool) ($data['mark_for_review'] ?? false),
        ]);

        $categories = $household->categories()->pluck('name', 'id');
        $buckets = $household->buckets()->pluck('name', 'id');

        $matches = $this->rules->matchingTransactions($household, $data['pattern']);

        $rows = $matches->take(50)->map(function ($transaction) use ($draft, $categories, $buckets): array {
            $after = $transaction->replicate();
            $this->rules->applyTo($draft, $after);

            return [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'amount' => Money::format($transaction->amount),
                'payee_before' => $transaction->payee,
                'payee_after' => $after->payee,
                'category_before' => $transaction->category_id ? $categories[$transaction->category_id] ?? null : null,
                'category_after' => $after->category_id ? $categories[$after->category_id] ?? null : null,
                'bucket_before' => $transaction->bucket_id ? $buckets[$transaction->bucket_id] ?? null : null,
                'bucket_after' => $after->bucket_id ? $buckets[$after->bucket_id] ?? null : null,
                'hidden' => $after->excluded_from_reports,
                'needs_review' => ! $after->reviewed,
            ];
        })->values();

        return response()->json([
            'total' => $matches->count(),
            'rows' => $rows,
        ]);
    }
}
