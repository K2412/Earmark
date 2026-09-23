<?php

namespace App\Models;

use Database\Factories\ReconciliationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    /** @use HasFactory<ReconciliationFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'account_id',
        'statement_date',
        'statement_balance',
        'calculated_balance',
        'status',
        'discrepancy_amount',
        'notes',
        'reconciled_by_user_id',
        'reconciled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'integer',
            'calculated_balance' => 'integer',
            'discrepancy_amount' => 'integer',
            'reconciled_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }
}
