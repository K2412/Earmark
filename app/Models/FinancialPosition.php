<?php

namespace App\Models;

use App\Enums\PositionClassification;
use App\Enums\PositionPurpose;
use Database\Factories\FinancialPositionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialPosition extends Model
{
    /** @use HasFactory<FinancialPositionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'owner_user_id',
        'name',
        'classification',
        'purpose',
        'archived',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => PositionClassification::class,
            'purpose' => PositionPurpose::class,
            'archived' => 'boolean',
            'sort_order' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<Valuation, $this>
     */
    public function valuations(): HasMany
    {
        return $this->hasMany(Valuation::class);
    }

    public function latestValuation(): ?Valuation
    {
        return $this->valuations()->orderByDesc('valued_at')->orderByDesc('id')->first();
    }
}
