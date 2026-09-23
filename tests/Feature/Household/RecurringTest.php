<?php

use App\Models\Account;
use App\Models\RecurringSchedule;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Recurring\RecurringCashFlowService;

function recurringHistory(User $user, string $payee, array $dates, int $amount = -1699): void
{
    $household = $user->household();
    $account = Account::factory()->create(['household_id' => $household->id]);

    foreach ($dates as $date) {
        Transaction::factory()->create([
            'household_id' => $household->id,
            'account_id' => $account->id,
            'payee' => $payee,
            'amount' => $amount,
            'date' => $date,
            'reviewed' => true,
            'category_id' => null,
            'bucket_id' => null,
            'created_by_user_id' => $user->id,
        ]);
    }
}

test('a regular payee is detected as an explainable monthly candidate', function () {
    $user = User::factory()->create();
    recurringHistory($user, 'Netflix', ['2026-01-15', '2026-02-15', '2026-03-15', '2026-04-15']);

    $candidates = app(RecurringCashFlowService::class)->detectCandidates($user->household());

    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['name'])->toBe('Netflix')
        ->and($candidates[0]['frequency'])->toBe('monthly')
        ->and($candidates[0]['occurrences'])->toBe(4)
        ->and($candidates[0]['amount_min'])->toBe(-1699);
});

test('irregular or sparse history produces no candidate', function () {
    $user = User::factory()->create();
    recurringHistory($user, 'OneOff', ['2026-01-01', '2026-01-20']); // only 2
    recurringHistory($user, 'Random', ['2026-01-01', '2026-02-03', '2026-05-20']); // irregular gaps

    expect(app(RecurringCashFlowService::class)->detectCandidates($user->household()))->toBe([]);
});

test('confirming a candidate persists it and stops re-detection', function () {
    $user = User::factory()->create();
    recurringHistory($user, 'Netflix', ['2026-01-15', '2026-02-15', '2026-03-15']);

    $this->actingAs($user)
        ->post(route('household.recurring.store'), [
            'name' => 'Netflix',
            'amount' => -1699,
            'frequency' => 'monthly',
            'next_due_date' => '2026-05-15',
            'status' => 'confirmed',
            'detected' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('recurring_schedules', ['name' => 'Netflix', 'status' => 'confirmed', 'detected' => true]);
    expect(app(RecurringCashFlowService::class)->detectCandidates($user->household()))->toBe([]);
});

test('dismissing a candidate suppresses it without a confirmed schedule', function () {
    $user = User::factory()->create();
    recurringHistory($user, 'Netflix', ['2026-01-15', '2026-02-15', '2026-03-15']);

    $this->actingAs($user)->post(route('household.recurring.store'), [
        'name' => 'Netflix', 'amount' => -1699, 'frequency' => 'monthly', 'status' => 'dismissed', 'detected' => true,
    ]);

    expect(app(RecurringCashFlowService::class)->detectCandidates($user->household()))->toBe([]);
    expect(app(RecurringCashFlowService::class)->upcoming($user->household()))->toHaveCount(0);
});

test('a manual schedule appears in upcoming cash flow', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('household.recurring.store'), [
        'name' => 'Rent', 'amount' => -180000, 'frequency' => 'monthly', 'next_due_date' => '2026-07-01', 'status' => 'confirmed',
    ])->assertSessionHasNoErrors();

    expect(app(RecurringCashFlowService::class)->upcoming($user->household()))->toHaveCount(1);
});

test('a schedule can be paused and leaves upcoming', function () {
    $user = User::factory()->create();
    $schedule = RecurringSchedule::factory()->create([
        'household_id' => $user->household()->id,
        'status' => 'confirmed',
        'next_due_date' => '2026-07-01',
    ]);

    $this->actingAs($user)->patch(route('household.recurring.update', $schedule), [
        'name' => $schedule->name, 'amount' => $schedule->amount, 'frequency' => 'monthly',
        'next_due_date' => '2026-07-01', 'status' => 'paused',
    ])->assertSessionHasNoErrors();

    expect(app(RecurringCashFlowService::class)->upcoming($user->household()))->toHaveCount(0);
});

test('the index separates confirmed schedules from detected candidates', function () {
    $user = User::factory()->create();
    RecurringSchedule::factory()->create(['household_id' => $user->household()->id, 'name' => 'Rent', 'status' => 'confirmed']);
    recurringHistory($user, 'Netflix', ['2026-01-15', '2026-02-15', '2026-03-15']);

    $this->actingAs($user)
        ->get(route('household.recurring.index'))
        ->assertInertia(fn ($page) => $page
            ->component('household/Recurring')
            ->has('schedules', 1)
            ->has('candidates', 1)
        );
});

test('a user cannot update another household schedule', function () {
    $user = User::factory()->create();
    $other = RecurringSchedule::factory()->create();

    $this->actingAs($user)->patch(route('household.recurring.update', $other), [
        'name' => 'x', 'amount' => -1, 'frequency' => 'monthly', 'status' => 'paused',
    ])->assertForbidden();
});
