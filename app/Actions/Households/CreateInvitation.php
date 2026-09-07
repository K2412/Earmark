<?php

namespace App\Actions\Households;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateInvitation
{
    use AsAction;

    /**
     * @param  array{email: string, role: string}  $data
     */
    public function handle(array $data, Household $household, User $invitedBy): HouseholdInvitation
    {
        return HouseholdInvitation::create([
            'household_id' => $household->id,
            'email' => $data['email'],
            'role' => HouseholdRole::from($data['role']),
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(14),
        ]);
    }
}
