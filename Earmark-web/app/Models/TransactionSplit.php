<?php

namespace App\Models;

use Database\Factories\TransactionSplitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionSplit extends Model
{
    /** @use HasFactory<TransactionSplitFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'transaction_id',
        'category_id',
        'bucket_id',
        'amount',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}
