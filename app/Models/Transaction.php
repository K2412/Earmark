<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'date',
        'account_id',
        'payee',
        'category_id',
        'bucket_id',
        'amount',
        'memo',
        'is_split',
        'cleared',
        'reviewed',
        'reconciled',
        'transfer_pair_id',
        'source',
        'import_batch_id',
        'created_by_user_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Transaction $transaction) {
            if (empty($transaction->household_id) && $transaction->account_id) {
                $transaction->household_id = Account::query()->find($transaction->account_id)?->household_id;
            }
        });

        static::deleting(function (Transaction $transaction) {
            if (! $transaction->transfer_pair_id) {
                return;
            }

            static::query()
                ->where('transfer_pair_id', $transaction->transfer_pair_id)
                ->where('id', '!=', $transaction->id)
                ->get()
                ->each(function (Transaction $sibling) {
                    $sibling->transfer_pair_id = null;
                    $sibling->saveQuietly();
                    $sibling->delete();
                });
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'is_split' => 'boolean',
            'cleared' => 'boolean',
            'reviewed' => 'boolean',
            'reconciled' => 'boolean',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /**
     * @return HasMany<TransactionSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /**
     * @return HasMany<TransactionActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TransactionActivity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
