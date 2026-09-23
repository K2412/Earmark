<?php

namespace App\Models;

use Database\Factories\StagedTransactionSplitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagedTransactionSplit extends Model
{
    /** @use HasFactory<StagedTransactionSplitFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'staged_transaction_id',
        'category_id',
        'bucket_id',
        'amount',
        'memo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StagedTransaction, $this>
     */
    public function stagedTransaction(): BelongsTo
    {
        return $this->belongsTo(StagedTransaction::class);
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
}
