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
        'household_id',
        'pattern',
        'category_id',
        'bucket_id',
        'priority',
        'auto_apply',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'auto_apply' => 'boolean',
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

    public function matches(string $payee): bool
    {
        if ($this->pattern === '') {
            return false;
        }

        return stripos($payee, $this->pattern) !== false;
    }
}
