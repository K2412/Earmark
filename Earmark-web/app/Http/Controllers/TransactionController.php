<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Services\Transaction\TransactionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(): Response
    {
        return inertia('household/transactions/Index');
    }

    public function store(StoreTransactionRequest $request, TransactionService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return to_route('household.transactions.index');
    }
}
