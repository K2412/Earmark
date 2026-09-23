<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasUlids;

    /**
     * Allowed account types (value => label). Registered Canadian accounts are
     * first-class alongside everyday operational accounts.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'chequing' => 'Chequing',
        'savings' => 'Savings',
        'credit_card' => 'Credit card',
        'cash' => 'Cash',
        'tfsa' => 'TFSA',
        'rrsp' => 'RRSP',
        'fhsa' => 'FHSA',
        'resp' => 'RESP',
        'taxable_investment' => 'Taxable investment',
        'investment' => 'Investment',
        'mortgage' => 'Mortgage',
        'loan' => 'Loan',
        'other' => 'Other',
    ];

    protected $fillable = [
        'household_id',
        'owner_user_id',
        'name',
        'institution',
        'type',
        'currency',
        'starting_balance',
        'starting_balance_date',
        'archived',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starting_balance' => 'integer',
            'starting_balance_date' => 'date',
            'archived' => 'boolean',
            'sort_order' => 'integer',
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
     * The household member who owns this account, or null when it is joint.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
