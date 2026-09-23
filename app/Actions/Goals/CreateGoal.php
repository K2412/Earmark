<?php

namespace App\Actions\Goals;

use App\Models\Goal;
use App\Models\Household;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateGoal
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $user, Household $household): Goal
    {
        return Goal::query()->create([
            ...$data,
            'household_id' => $household->id,
            'created_by_user_id' => $user->id,
        ]);
    }
}
