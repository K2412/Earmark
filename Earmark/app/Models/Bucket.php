<?php

namespace App\Models;

use Database\Factories\BucketFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

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

    protected static function boot(): void
    {
        parent::boot();

        // System buckets (e.g. Unassigned Funds) must never be destroyed —
        // they're load-bearing for the budget math. Policies enforce this at
        // the UI; this is defense-in-depth at the model.
        static::deleting(function (Bucket $bucket) {
            if ($bucket->isProtected()) {
                throw new RuntimeException("Cannot delete protected system bucket [{$bucket->name}].");
            }
        });
    }

    public function isProtected(): bool
    {
        return $this->kind === 'system';
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
