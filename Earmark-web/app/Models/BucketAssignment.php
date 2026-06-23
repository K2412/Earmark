<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BucketAssignment extends Model
{
    use HasUlids;

    protected $fillable = [
        'from_bucket_id',
        'to_bucket_id',
        'year',
        'month',
        'amount',
        'memo',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function fromBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'from_bucket_id');
    }

    public function toBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'to_bucket_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
