<?php

namespace App\Models;

use Database\Factories\RecurringScheduleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringSchedule extends Model
{
    /** @use HasFactory<RecurringScheduleFactory> */
    use HasFactory, HasUlids;

    public const FREQUENCIES = ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'household_id',
        'name',
        'amount',
        'frequency',
        'next_due_date',
        'status',
        'detected',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'next_due_date' => 'date',
            'detected' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
