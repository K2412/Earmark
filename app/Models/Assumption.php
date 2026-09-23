<?php

namespace App\Models;

use Database\Factories\AssumptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assumption extends Model
{
    /** @use HasFactory<AssumptionFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'key',
        'jurisdiction',
        'value',
        'unit',
        'effective_year',
        'source_url',
        'source_date',
        'catalogue_version',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'effective_year' => 'integer',
            'source_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function isOverride(): bool
    {
        return $this->household_id !== null;
    }
}
