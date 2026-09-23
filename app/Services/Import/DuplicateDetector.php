<?php

namespace App\Services\Import;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Import\DraftRow;
use App\Support\Money;

/**
 * Flags draft rows that may duplicate an existing ledger transaction or another
 * row in the same batch. Ambiguous rows are reported with a reason, never
 * silently dropped (task #1247, user story 7). Deciding what to do with a flagged
 * row is the reviewer's job during promotion (#1249).
 */
class DuplicateDetector
{
    /**
     * @param  list<DraftRow>  $drafts
     * @return list<array{draft: DraftRow, fingerprint: string, duplicate_of_transaction_id: ?string, reason: ?string}>
     */
    public function inspect(Account $account, array $drafts): array
    {
        $seen = [];
        $results = [];

        foreach ($drafts as $draft) {
            $fingerprint = $this->fingerprint($account->id, $draft);
            $duplicateOfId = null;
            $reason = null;

            if (isset($seen[$fingerprint])) {
                $reason = 'Appears more than once in this file.';
            } else {
                $existing = Transaction::query()
                    ->where('account_id', $account->id)
                    ->whereDate('date', $draft->date)
                    ->where('amount', $draft->amountCents)
                    ->first();

                if ($existing !== null) {
                    $reason = sprintf(
                        'Matches an existing transaction on %s for %s.',
                        $draft->date,
                        Money::format($draft->amountCents),
                    );
                    $duplicateOfId = $existing->id;
                }
            }

            $seen[$fingerprint] = true;

            $results[] = [
                'draft' => $draft,
                'fingerprint' => $fingerprint,
                'duplicate_of_transaction_id' => $duplicateOfId,
                'reason' => $reason,
            ];
        }

        return $results;
    }

    public function fingerprint(string $accountId, DraftRow $draft): string
    {
        return hash('sha256', implode('|', [
            $accountId,
            $draft->date,
            $draft->amountCents,
            mb_strtolower(trim($draft->payee)),
        ]));
    }
}
