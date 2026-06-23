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
        'date',
        'account_id',
        'payee',
        'category_id',
        'bucket_id',
        'amount',
        'memo',
        'is_split',
        'cleared',
        'reconciled',
        'transfer_pair_id',
        'source',
        'import_batch_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'is_split' => 'boolean',
            'cleared' => 'boolean',
            'reconciled' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
