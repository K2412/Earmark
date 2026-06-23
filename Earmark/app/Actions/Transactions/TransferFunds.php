<?php

namespace App\Actions\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class TransferFunds
{
    use AsAction;

    /**
     * Create two Transaction rows sharing transfer_pair_id — one negative on
     * the source account, one positive on the destination. Atomic.
     *
     * @param  array{date: string, from_account_id: string, to_account_id: string, amount: int, memo: ?string}  $data
     * @return array{0: Transaction, 1: Transaction}
     */
    public function handle(array $data, User $user): array
    {
        if ($data['from_account_id'] === $data['to_account_id']) {
            throw new InvalidArgumentException('Transfer source and destination must be different accounts.');
        }

        if ($data['amount'] <= 0) {
            throw new InvalidArgumentException('Transfer amount must be positive cents.');
        }

        return DB::transaction(function () use ($data, $user) {
            $pairId = (string) Str::ulid();
            $payee = 'Transfer';
            $memo = $data['memo'] ?? null;

            $out = Transaction::query()->create([
                'date' => $data['date'],
                'account_id' => $data['from_account_id'],
                'payee' => $payee,
                'category_id' => null,
                'bucket_id' => null,
                'amount' => -$data['amount'],
                'memo' => $memo,
                'is_split' => false,
                'source' => 'manual',
                'transfer_pair_id' => $pairId,
                'created_by_user_id' => $user->id,
            ]);

            $in = Transaction::query()->create([
                'date' => $data['date'],
                'account_id' => $data['to_account_id'],
                'payee' => $payee,
                'category_id' => null,
                'bucket_id' => null,
                'amount' => $data['amount'],
                'memo' => $memo,
                'is_split' => false,
                'source' => 'manual',
                'transfer_pair_id' => $pairId,
                'created_by_user_id' => $user->id,
            ]);

            return [$out, $in];
        });
    }
}
