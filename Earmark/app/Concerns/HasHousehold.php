<?php

namespace App\Concerns;

use App\Data\HouseholdPermissions;
use App\Enums\HouseholdPermission;
use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdMembership;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait HasHousehold
{
    /**
     * The households the user belongs to. App-level constraint: at most one.
     *
     * @return BelongsToMany<Household, $this>
     */
    public function households(): BelongsToMany
    {
        return $this->belongsToMany(Household::class, 'household_members', 'user_id', 'household_id')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * The user's single household — single-household model convenience.
     */
    public function household(): ?Household
    {
        return $this->households()->first();
    }

    /**
     * @return HasManyThrough<Household, HouseholdMembership, $this>
     */
    public function ownedHouseholds(): HasManyThrough
    {
        return $this->hasManyThrough(
            Household::class,
            HouseholdMembership::class,
            'user_id',
            'id',
            'id',
            'household_id',
        )->where('household_members.role', HouseholdRole::Owner->value);
    }

    /**
     * @return HasMany<HouseholdMembership, $this>
     */
    public function householdMemberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class, 'user_id');
    }

    public function belongsToHousehold(Household $household): bool
    {
        return $this->households()->where('households.id', $household->id)->exists();
    }

    public function ownsHousehold(Household $household): bool
    {
        return $this->householdRole($household) === HouseholdRole::Owner;
    }

    public function householdRole(Household $household): ?HouseholdRole
    {
        return $this->householdMemberships()
            ->where('household_id', $household->id)
            ->first()
            ?->role;
    }

    public function toHouseholdPermissions(Household $household): HouseholdPermissions
    {
        $role = $this->householdRole($household);

        return new HouseholdPermissions(
            canUpdateHousehold: $role?->hasPermission(HouseholdPermission::UpdateHousehold) ?? false,
            canDeleteHousehold: $role?->hasPermission(HouseholdPermission::DeleteHousehold) ?? false,
            canAddMember: $role?->hasPermission(HouseholdPermission::AddMember) ?? false,
            canUpdateMember: $role?->hasPermission(HouseholdPermission::UpdateMember) ?? false,
            canRemoveMember: $role?->hasPermission(HouseholdPermission::RemoveMember) ?? false,
            canCreateInvitation: $role?->hasPermission(HouseholdPermission::CreateInvitation) ?? false,
            canCancelInvitation: $role?->hasPermission(HouseholdPermission::CancelInvitation) ?? false,
        );
    }

    public function hasHouseholdPermission(Household $household, HouseholdPermission $permission): bool
    {
        return $this->householdRole($household)?->hasPermission($permission) ?? false;
    }
}
