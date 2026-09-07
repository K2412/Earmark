<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BucketAssignment extends Model
{
    use HasUlids;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BucketAssignment $assignment) {
            if (empty($assignment->household_id) && $assignment->to_bucket_id) {
                $assignment->household_id = Bucket::query()->find($assignment->to_bucket_id)?->household_id;
            }
        });
    }

    protected $fillable = [
        'household_id',
        'from_bucket_id',
        'to_bucket_id',
        'year',
        'month',
        'amount',
        'memo',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'integer',
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
     * @return BelongsTo<Bucket, $this>
     */
    public function fromBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'from_bucket_id');
    }

    /**
     * @return BelongsTo<Bucket, $this>
     */
    public function toBucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class, 'to_bucket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
