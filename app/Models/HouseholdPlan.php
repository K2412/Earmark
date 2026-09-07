<?php

namespace App\Models;

use Database\Factories\HouseholdPlanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HouseholdPlan extends Model
{
    /** @use HasFactory<HouseholdPlanFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'target_cents',
        'target_year',
        'inflation_bps',
        'return_bps',
        'windfall_cents',
        'windfall_year',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_cents' => 'integer',
            'target_year' => 'integer',
            'inflation_bps' => 'integer',
            'return_bps' => 'integer',
            'windfall_cents' => 'integer',
            'windfall_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * @return HasMany<ContributionPhase, $this>
     */
    public function contributionPhases(): HasMany
    {
        return $this->hasMany(ContributionPhase::class)->orderBy('sort_order')->orderBy('start_year');
    }
}
