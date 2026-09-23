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
        'name',
        'pattern',
        'enabled',
        'category_id',
        'bucket_id',
        'rename_to',
        'hide_from_reports',
        'mark_for_review',
        'priority',
        'auto_apply',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'hide_from_reports' => 'boolean',
            'mark_for_review' => 'boolean',
            'priority' => 'integer',
            'auto_apply' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->name ?: $this->pattern;
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
