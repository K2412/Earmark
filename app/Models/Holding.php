<?php

namespace App\Models;

use Database\Factories\HoldingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Holding extends Model
{
    /** @use HasFactory<HoldingFactory> */
    use HasFactory, HasUlids;

    public const ASSET_CLASSES = ['equity', 'fixed_income', 'cash', 'real_estate', 'other'];

    protected $fillable = [
        'household_id',
        'account_id',
        'name',
        'symbol',
        'asset_class',
        'cost_basis_cents',
        'market_value_cents',
        'target_allocation_bps',
        'currency',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_basis_cents' => 'integer',
            'market_value_cents' => 'integer',
            'target_allocation_bps' => 'integer',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
