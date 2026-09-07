<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributionPhase extends Model
{
    use HasUlids;

    protected $fillable = [
        'household_plan_id',
        'start_year',
        'end_year',
        'annual_contribution_cents',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
            'annual_contribution_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<HouseholdPlan, $this>
     */
    public function householdPlan(): BelongsTo
    {
        return $this->belongsTo(HouseholdPlan::class);
    }
}
