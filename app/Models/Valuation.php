<?php

namespace App\Models;

use Database\Factories\ValuationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Valuation extends Model
{
    /** @use HasFactory<ValuationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'financial_position_id',
        'amount',
        'valued_at',
        'archived',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'valued_at' => 'date',
            'archived' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<FinancialPosition, $this>
     */
    public function financialPosition(): BelongsTo
    {
        return $this->belongsTo(FinancialPosition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
