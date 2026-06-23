<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionSplitRequest;
use App\Models\Transaction;
use App\Services\Transaction\SplitTransactionService;
use Illuminate\Http\RedirectResponse;

class TransactionSplitController extends Controller
{
    public function store(
        StoreTransactionSplitRequest $request,
        Transaction $transaction,
        SplitTransactionService $service,
    ): RedirectResponse {
        $service->split($transaction, $request->validated('splits'));

        return to_route('household.transactions.index');
    }
}
