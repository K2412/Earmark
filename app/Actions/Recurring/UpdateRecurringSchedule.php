<?php

namespace App\Actions\Recurring;

use App\Models\RecurringSchedule;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateRecurringSchedule
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(RecurringSchedule $schedule, array $data): RecurringSchedule
    {
        unset($data['household_id'], $data['detected']);

        $schedule->fill($data)->save();

        return $schedule;
    }
}
