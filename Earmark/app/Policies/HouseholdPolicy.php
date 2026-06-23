<?php

namespace App\Policies;

use App\Enums\HouseholdPermission;
use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Household $household): bool
    {
        return $user->belongsToHousehold($household);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::UpdateHousehold);
    }

    /**
     * Determine whether the user can add a member to the household.
     */
    public function addMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the household.
     */
    public function updateMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the household.
     */
    public function removeMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the household.
     */
    public function inviteMember(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Household $household): bool
    {
        return $user->hasHouseholdPermission($household, HouseholdPermission::DeleteHousehold);
    }
}
