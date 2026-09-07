<?php

namespace App\Actions\Transactions;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionService;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateTransaction
{
    use AsAction;

    public function __construct(private TransactionService $transactions) {}

    /**
     * @param  array{date: string, account_id: string, payee: string, category_id: ?string, bucket_id: ?string, amount: int, memo: ?string}  $data
     */
    public function handle(array $data, User $user, Household $household): Transaction
    {
        return $this->transactions->create([
            ...$data,
            'source' => 'manual',
        ], $user, $household);
    }
}
