<?php

namespace App\Models;

use Database\Factories\StagedTransactionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StagedTransaction extends Model
{
    /** @use HasFactory<StagedTransactionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'statement_upload_id',
        'date',
        'payee',
        'raw_payee',
        'row_fingerprint',
        'external_id',
        'amount',
        'suggested_category_id',
        'suggested_bucket_id',
        'final_category_id',
        'final_bucket_id',
        'accept',
        'status',
        'is_possible_duplicate',
        'duplicate_reason',
        'duplicate_of_transaction_id',
        'is_split',
        'transaction_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'accept' => 'boolean',
            'is_possible_duplicate' => 'boolean',
            'is_split' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<StatementUpload, $this>
     */
    public function statementUpload(): BelongsTo
    {
        return $this->belongsTo(StatementUpload::class);
    }

    /**
     * @return HasMany<StagedTransactionSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(StagedTransactionSplit::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function suggestedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function suggestedBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'suggested_bucket_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function finalCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'final_category_id');
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function finalBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'final_bucket_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * The existing ledger transaction this staged row may duplicate, if any.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'duplicate_of_transaction_id');
    }
}
