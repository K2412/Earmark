<?php

namespace App\Models;

use Database\Factories\PayeeRuleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayeeRule extends Model
{
    /** @use HasFactory<PayeeRuleFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'pattern',
        'category_id',
        'bucket_id',
        'priority',
        'auto_apply',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'auto_apply' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /**
     * Match this rule's pattern against a payee string. Treats the pattern
     * as a case-insensitive substring (the simplest useful starting point;
     * regex patterns would be a future enhancement).
     */
    public function matches(string $payee): bool
    {
        if ($this->pattern === '') {
            return false;
        }

        return stripos($payee, $this->pattern) !== false;
    }
}
