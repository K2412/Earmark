<?php

namespace App\Actions\Import;

use App\Models\Household;
use App\Models\StagedTransaction;
use App\Models\StatementUpload;
use App\Models\Transaction;
use App\Models\TransactionSplit;
use App\Models\User;
use App\Services\Transaction\TransactionService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Promotes accepted, pending staged rows into the ledger in one atomic action:
 * either every selected valid row commits with provenance, or none does. Already
 * promoted rows are skipped, so retrying a completed promotion creates no
 * duplicate transactions (task #1249, user story 6).
 */
class PromoteStatementImport
{
    use AsAction;

    public function __construct(private TransactionService $transactions) {}

    /**
     * @return array{promoted: int, failed: int, rejected: int, pending: int}
     */
    public function handle(StatementUpload $upload, User $user, Household $household): array
    {
        $rows = $upload->stagedTransactions()->with('splits')->get();

        $rejected = $rows->where('status', 'rejected')->count();
        $selected = $rows->filter(fn (StagedTransaction $row): bool => $row->status === 'pending' && $row->accept);

        $valid = $selected->filter(fn (StagedTransaction $row): bool => $this->isValid($row))->values();
        $failed = $selected->count() - $valid->count();

        DB::transaction(function () use ($valid, $user, $household, $upload): void {
            foreach ($valid as $row) {
                $this->promoteRow($row, $user, $household, $upload);
            }

            $upload->update([
                'status' => 'imported',
                'imported_transaction_count' => $upload->stagedTransactions()->where('status', 'promoted')->count(),
            ]);
        });

        return [
            'promoted' => $valid->count(),
            'failed' => $failed,
            'rejected' => $rejected,
            'pending' => $rows->where('status', 'pending')->count() - $valid->count(),
        ];
    }

    private function isValid(StagedTransaction $row): bool
    {
        if (! $row->is_split) {
            return true;
        }

        if ($row->splits->isEmpty()) {
            return false;
        }

        return $row->splits->sum('amount') === $row->amount;
    }

    private function promoteRow(StagedTransaction $row, User $user, Household $household, StatementUpload $upload): void
    {
        if ($row->transaction_id !== null) {
            return;
        }

        $source = match ($upload->source) {
            'csv' => 'imported_csv',
            'qif' => 'imported_qif',
            'ofx', 'qfx', 'qbo' => 'imported_ofx',
            default => 'imported_pdf',
        };

        if ($row->is_split) {
            $transaction = Transaction::query()->create([
                'household_id' => $household->id,
                'account_id' => $upload->account_id,
                'date' => $row->date->toDateString(),
                'payee' => $row->payee,
                'amount' => $row->amount,
                'is_split' => true,
                'source' => $source,
                'import_batch_id' => $upload->id,
                'external_id' => $row->external_id,
                'created_by_user_id' => $user->id,
                'cleared' => false,
            ]);

            foreach ($row->splits as $split) {
                TransactionSplit::query()->create([
                    'transaction_id' => $transaction->id,
                    'category_id' => $split->category_id,
                    'bucket_id' => $split->bucket_id,
                    'amount' => $split->amount,
                    'memo' => $split->memo,
                ]);
            }
        } else {
            $transaction = $this->transactions->create([
                'date' => $row->date->toDateString(),
                'account_id' => $upload->account_id,
                'payee' => $row->payee,
                'category_id' => $row->final_category_id,
                'bucket_id' => $row->final_bucket_id,
                'amount' => $row->amount,
                'source' => $source,
                'import_batch_id' => $upload->id,
                'external_id' => $row->external_id,
            ], $user, $household);
        }

        $row->update(['status' => 'promoted', 'transaction_id' => $transaction->id]);
    }
}
