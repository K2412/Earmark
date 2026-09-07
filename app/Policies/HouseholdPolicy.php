<?php

namespace App\Policies;

use App\Enums\HouseholdPermission;
use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Household $household): bool
    {
        return $user->belongsToHousehold($household);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::UpdateHousehold);
    }

    public function addMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::AddMember);
    }

    public function updateMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::UpdateMember);
    }

    public function removeMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::RemoveMember);
    }

    public function inviteMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::CreateInvitation);
    }

    public function cancelInvitation(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::CancelInvitation);
    }

    public function delete(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::DeleteHousehold);
    }
}
