<?php

namespace App\Actions\Transactions;

use App\Models\Household;
use App\Models\User;
use App\Services\Transaction\TransactionActivityLogger;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Bulk-sets the review state on household transactions and records one activity
 * entry for the batch (task #1246).
 */
class MarkTransactionsReviewed
{
    use AsAction;

    public function __construct(private TransactionActivityLogger $activity) {}

    /**
     * @param  list<string>  $ids
     */
    public function handle(array $ids, bool $reviewed, User $user, Household $household): int
    {
        $count = $household->transactions()
            ->whereIn('id', $ids)
            ->whereNull('transfer_pair_id')
            ->update($reviewed ? ['reviewed' => true, 'review_assignee_id' => null] : ['reviewed' => false]);

        if ($count > 0) {
            $this->activity->log(
                $household,
                $user,
                null,
                $reviewed ? 'reviewed' : 'unreviewed',
                sprintf('Marked %d transaction(s) as %s', $count, $reviewed ? 'reviewed' : 'not reviewed'),
            );
        }

        return $count;
    }
}
