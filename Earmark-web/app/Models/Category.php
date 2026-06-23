<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'type',
        'sort_order',
        'archived',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'archived' => 'boolean',
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
}
