<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueHouseholdSlugs;
use App\Enums\HouseholdRole;
use Database\Factories\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug'])]
class Household extends Model
{
    /** @use HasFactory<HouseholdFactory> */
    use GeneratesUniqueHouseholdSlugs, HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Household $household) {
            if (empty($household->slug)) {
                $household->slug = static::generateUniqueHouseholdSlug($household->name);
            }
        });

        static::updating(function (Household $household) {
            if ($household->isDirty('name')) {
                $household->slug = static::generateUniqueHouseholdSlug($household->name, $household->id);
            }
        });
    }

    /**
     * Get the household owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('role', HouseholdRole::Owner->value)
            ->first();
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'household_members', 'household_id', 'user_id')
            ->using(HouseholdMembership::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<HouseholdMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(HouseholdMembership::class);
    }

    /**
     * @return HasMany<HouseholdInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(HouseholdInvitation::class);
    }
}
