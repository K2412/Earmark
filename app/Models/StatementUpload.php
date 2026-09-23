<?php

namespace App\Models;

use Database\Factories\StatementUploadFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StatementUpload extends Model
{
    /** @use HasFactory<StatementUploadFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'household_id',
        'account_id',
        'source',
        'original_filename',
        'file_sha256',
        'file_size_bytes',
        'status',
        'parser_version',
        'parsed_transaction_count',
        'imported_transaction_count',
        'error_message',
        'uploaded_by_user_id',
        'uploaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'parsed_transaction_count' => 'integer',
            'imported_transaction_count' => 'integer',
            'uploaded_at' => 'datetime',
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
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<StagedTransaction, $this>
     */
    public function stagedTransactions(): HasMany
    {
        return $this->hasMany(StagedTransaction::class);
    }
}
