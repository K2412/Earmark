<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BucketObligationVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'bucket_id',
        'monthly_obligation',
        'target_amount',
        'target_date',
        'effective_year',
        'effective_month',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'monthly_obligation' => 'integer',
            'target_amount' => 'integer',
            'target_date' => 'date',
            'effective_year' => 'integer',
            'effective_month' => 'integer',
        ];
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
