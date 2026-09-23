<?php

namespace App\Http\Controllers\Household;

use App\Actions\Recurring\SaveRecurringSchedule;
use App\Actions\Recurring\UpdateRecurringSchedule;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreRecurringScheduleRequest;
use App\Http\Requests\Household\UpdateRecurringScheduleRequest;
use App\Models\RecurringSchedule;
use App\Services\Recurring\RecurringCashFlowService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringController extends Controller
{
    use ResolvesHousehold;

    public function __construct(private RecurringCashFlowService $recurring) {}

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $schedules = RecurringSchedule::query()
            ->where('household_id', $household->id)
            ->whereIn('status', ['confirmed', 'paused'])
            ->orderBy('next_due_date')
            ->get()
            ->map(fn (RecurringSchedule $schedule): array => [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'amount' => $schedule->amount,
                'amount_formatted' => Money::format($schedule->amount),
                'frequency' => $schedule->frequency,
                'next_due_date' => $schedule->next_due_date?->toDateString(),
                'status' => $schedule->status,
                'detected' => $schedule->detected,
            ]);

        $candidates = collect($this->recurring->detectCandidates($household))
            ->map(fn (array $candidate): array => [
                ...$candidate,
                'amount_range' => $candidate['amount_min'] === $candidate['amount_max']
                    ? Money::format($candidate['amount_min'])
                    : Money::format($candidate['amount_min']).' – '.Money::format($candidate['amount_max']),
            ]);

        return Inertia::render('household/Recurring', [
            'schedules' => $schedules,
            'candidates' => $candidates,
            'frequencies' => RecurringSchedule::FREQUENCIES,
            'defaults' => ['next_due_date' => now()->addMonth()->toDateString()],
        ]);
    }

    public function store(StoreRecurringScheduleRequest $request, SaveRecurringSchedule $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring item saved.')]);

        return to_route('household.recurring.index');
    }

    public function update(UpdateRecurringScheduleRequest $request, RecurringSchedule $recurringSchedule, UpdateRecurringSchedule $action): RedirectResponse
    {
        $action->handle($recurringSchedule, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Recurring item updated.')]);

        return back();
    }
}
