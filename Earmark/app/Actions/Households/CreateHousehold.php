<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHousehold
{
    /**
     * Create a household and attach the user as Owner.
     */
    public function handle(User $user, string $name): Household
    {
        return DB::transaction(function () use ($user, $name) {
            $household = Household::create(['name' => $name]);

            $household->memberships()->create([
                'user_id' => $user->id,
                'role' => HouseholdRole::Owner,
            ]);

            return $household;
        });
    }
}
