<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Edits both sides of a transfer pair as one balanced operation. The outflow and
 * inflow stay mirror images, so a transfer never registers as income, spending,
 * or budget consumption (task #1251).
 */
class UpdateTransfer
{
    use AsAction;

    /**
     * @param  array{date: string, from_account_id: string, to_account_id: string, amount: int, memo: ?string}  $data
     */
    public function handle(Transaction $outflow, array $data, User $user): void
    {
        if ($data['from_account_id'] === $data['to_account_id']) {
            throw new InvalidArgumentException('Transfer source and destination must be different accounts.');
        }

        if ($data['amount'] <= 0) {
            throw new InvalidArgumentException('Transfer amount must be positive cents.');
        }

        $inflow = Transaction::query()
            ->where('transfer_pair_id', $outflow->transfer_pair_id)
            ->where('id', '!=', $outflow->id)
            ->firstOrFail();

        DB::transaction(function () use ($outflow, $inflow, $data): void {
            $outflow->update([
                'date' => $data['date'],
                'account_id' => $data['from_account_id'],
                'amount' => -$data['amount'],
                'memo' => $data['memo'] ?? null,
            ]);

            $inflow->update([
                'date' => $data['date'],
                'account_id' => $data['to_account_id'],
                'amount' => $data['amount'],
                'memo' => $data['memo'] ?? null,
            ]);
        });
    }
}
