<?php

namespace App\Models;

use Database\Factories\ScenarioFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scenario extends Model
{
    /** @use HasFactory<ScenarioFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'name',
        'base_year',
        'horizon_years',
        'starting_investable_cents',
        'annual_contribution_cents',
        'contribution_years',
        'return_bps',
        'inflation_bps',
        'windfall_cents',
        'windfall_year',
        'drawdown_start_year',
        'drawdown_annual_cents',
        'downsizing_year',
        'downsizing_proceeds_cents',
        'assumptions_snapshot',
        'formula_version',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_year' => 'integer',
            'horizon_years' => 'integer',
            'starting_investable_cents' => 'integer',
            'annual_contribution_cents' => 'integer',
            'contribution_years' => 'integer',
            'return_bps' => 'integer',
            'inflation_bps' => 'integer',
            'windfall_cents' => 'integer',
            'windfall_year' => 'integer',
            'drawdown_start_year' => 'integer',
            'drawdown_annual_cents' => 'integer',
            'downsizing_year' => 'integer',
            'downsizing_proceeds_cents' => 'integer',
            'assumptions_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
