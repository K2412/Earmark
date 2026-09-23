<?php

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory, HasUlids;

    public const TYPES = ['save_up', 'pay_down'];

    protected $fillable = [
        'household_id',
        'name',
        'type',
        'target_amount',
        'target_date',
        'apr_bps',
        'required_payment',
        'principal',
        'linked_account_id',
        'linked_bucket_id',
        'linked_position_id',
        'sort_order',
        'status',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'target_date' => 'date',
            'apr_bps' => 'integer',
            'required_payment' => 'integer',
            'principal' => 'integer',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'linked_account_id');
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'linked_bucket_id');
    }

    /**
     * @return BelongsTo<FinancialPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(FinancialPosition::class, 'linked_position_id');
    }
}
