<?php

namespace App\Actions\Import;

use App\Models\StatementUpload;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Applies reviewer corrections to a statement's staged rows: field edits,
 * accept/reject state, and split allocations. Already promoted rows are left
 * untouched so provenance survives later edits (task #1249).
 */
class UpdateStatementReview
{
    use AsAction;

    /**
     * @param  list<array{id: string, date: string, payee: string, amount: int, final_category_id: ?string, final_bucket_id: ?string, accept: bool, status: string, splits?: list<array{category_id: ?string, bucket_id: string, amount: int, memo: ?string}>}>  $rows
     */
    public function handle(StatementUpload $upload, array $rows): void
    {
        DB::transaction(function () use ($upload, $rows): void {
            foreach ($rows as $data) {
                $staged = $upload->stagedTransactions()->whereKey($data['id'])->first();

                if ($staged === null || $staged->status === 'promoted') {
                    continue;
                }

                $splits = $data['splits'] ?? [];
                $isSplit = $splits !== [];

                $staged->update([
                    'date' => $data['date'],
                    'payee' => $data['payee'],
                    'amount' => $data['amount'],
                    'final_category_id' => $isSplit ? null : ($data['final_category_id'] ?? null),
                    'final_bucket_id' => $isSplit ? null : ($data['final_bucket_id'] ?? null),
                    'accept' => $data['accept'],
                    'status' => $data['status'],
                    'is_split' => $isSplit,
                ]);

                $staged->splits()->delete();

                foreach ($splits as $split) {
                    $staged->splits()->create([
                        'category_id' => $split['category_id'] ?? null,
                        'bucket_id' => $split['bucket_id'],
                        'amount' => $split['amount'],
                        'memo' => $split['memo'] ?? null,
                    ]);
                }
            }
        });
    }
}
