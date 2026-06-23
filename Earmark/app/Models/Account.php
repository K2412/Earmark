<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'name',
        'type',
        'starting_balance',
        'starting_balance_date',
        'archived',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starting_balance' => 'integer',
            'starting_balance_date' => 'date',
            'archived' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
