<?php

namespace App\Actions\Recurring;

use App\Models\Household;
use App\Models\RecurringSchedule;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates a recurring schedule — from a manual entry or a confirmed/dismissed
 * detection candidate. Confirming or dismissing a candidate persists a row that
 * also suppresses re-detection of that payee (task #1253).
 */
class SaveRecurringSchedule
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user, Household $household): RecurringSchedule
    {
        return RecurringSchedule::query()->create([
            'household_id' => $household->id,
            'name' => $data['name'],
            'amount' => $data['amount'],
            'frequency' => $data['frequency'],
            'next_due_date' => $data['next_due_date'] ?? null,
            'status' => $data['status'] ?? 'confirmed',
            'detected' => (bool) ($data['detected'] ?? false),
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $user->id,
        ]);
    }
}
