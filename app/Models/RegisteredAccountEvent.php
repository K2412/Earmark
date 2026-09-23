<?php

namespace App\Models;

use Database\Factories\RegisteredAccountEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisteredAccountEvent extends Model
{
    /** @use HasFactory<RegisteredAccountEventFactory> */
    use HasFactory, HasUlids;

    public const TYPES = ['contribution', 'hbp_withdrawal', 'hbp_repayment', 'room_override'];

    protected $fillable = [
        'household_id',
        'account_id',
        'owner_user_id',
        'type',
        'amount',
        'occurred_on',
        'plan_year',
        'as_of',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_on' => 'date',
            'plan_year' => 'integer',
            'as_of' => 'date',
        ];
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
