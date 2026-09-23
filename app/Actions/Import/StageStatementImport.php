<?php

namespace App\Actions\Import;

use App\Models\Account;
use App\Models\Household;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;
use App\Models\User;
use App\Services\Import\CsvImportNormalizer;
use App\Services\Import\DuplicateDetector;
use App\Services\Payee\PayeeRuleService;
use App\Support\Import\DraftRow;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Persists normalized draft rows as a statement upload plus staged transactions.
 * Staging never writes ledger rows — promotion is a separate atomic action (#1249).
 * Re-staging a file already imported for the household is a no-op, so retries are
 * safe and cannot create duplicate ledger rows.
 */
class StageStatementImport
{
    use AsAction;

    public function __construct(
        private DuplicateDetector $duplicates,
        private PayeeRuleService $payeeRules,
    ) {}

    /**
     * @param  array{account_id: string, source: string, original_filename: string, file_sha256: string, file_size_bytes: int, parser_version?: string}  $meta
     * @param  list<DraftRow>  $drafts
     */
    public function handle(array $meta, array $drafts, User $user, Household $household): StatementUpload
    {
        $account = Account::query()
            ->where('household_id', $household->id)
            ->findOrFail($meta['account_id']);

        return DB::transaction(function () use ($meta, $drafts, $user, $household, $account): StatementUpload {
            $existing = StatementUpload::query()
                ->where('household_id', $household->id)
                ->where('file_sha256', $meta['file_sha256'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $upload = StatementUpload::query()->create([
                'household_id' => $household->id,
                'account_id' => $account->id,
                'source' => $meta['source'],
                'original_filename' => $meta['original_filename'],
                'file_sha256' => $meta['file_sha256'],
                'file_size_bytes' => $meta['file_size_bytes'],
                'status' => 'parsed',
                'parser_version' => $meta['parser_version'] ?? CsvImportNormalizer::PARSER_VERSION,
                'parsed_transaction_count' => count($drafts),
                'imported_transaction_count' => 0,
                'uploaded_by_user_id' => $user->id,
                'uploaded_at' => now(),
            ]);

            foreach ($this->duplicates->inspect($account, $drafts) as $row) {
                $draft = $row['draft'];
                $suggestion = $this->payeeRules->suggest($draft->payee, $household);
                $isDuplicate = $row['reason'] !== null;

                StagedTransaction::query()->create([
                    'statement_upload_id' => $upload->id,
                    'date' => $draft->date,
                    'payee' => $draft->payee,
                    'raw_payee' => $draft->rawPayee,
                    'row_fingerprint' => $row['fingerprint'],
                    'amount' => $draft->amountCents,
                    'suggested_category_id' => $suggestion['category_id'],
                    'suggested_bucket_id' => $suggestion['bucket_id'],
                    'final_category_id' => $suggestion['category_id'],
                    'final_bucket_id' => $suggestion['bucket_id'],
                    'accept' => ! $isDuplicate,
                    'is_possible_duplicate' => $isDuplicate,
                    'duplicate_reason' => $row['reason'],
                    'duplicate_of_transaction_id' => $row['duplicate_of_transaction_id'],
                    'is_split' => false,
                ]);
            }

            return $upload;
        });
    }
}
