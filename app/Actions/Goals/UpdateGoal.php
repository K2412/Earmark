<?php

namespace App\Actions\Goals;

use App\Models\Goal;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateGoal
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Goal $goal, array $data): Goal
    {
        unset($data['household_id'], $data['created_by_user_id']);

        $goal->fill($data)->save();

        return $goal;
    }
}
