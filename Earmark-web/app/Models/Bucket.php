<?php

namespace App\Models;

use Database\Factories\BucketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bucket extends Model
{
    /** @use HasFactory<BucketFactory> */
    use HasFactory, HasUlids;

    public const UNASSIGNED_FUNDS = 'Unassigned Funds';

    protected $fillable = [
        'name',
        'kind',
        'monthly_obligation',
        'target_amount',
        'target_date',
        'archived',
        'archived_at',
        'sort_order',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_obligation' => 'integer',
            'target_amount' => 'integer',
            'target_date' => 'date',
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function transactionSplits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    public function obligationVersions(): HasMany
    {
        return $this->hasMany(BucketObligationVersion::class);
    }
}
