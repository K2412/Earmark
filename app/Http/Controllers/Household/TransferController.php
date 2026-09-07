<?php

namespace App\Http\Controllers\Household;

use App\Actions\Transactions\TransferFunds;
use App\Concerns\ResolvesHousehold;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\StoreTransferRequest;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    use ResolvesHousehold;

    public function index(Request $request): Response
    {
        $household = $this->household($request);

        $outflows = $household->transactions()
            ->with('account')
            ->whereNotNull('transfer_pair_id')
            ->where('amount', '<', 0)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();

        $siblings = Transaction::query()
            ->with('account')
            ->whereIn('transfer_pair_id', $outflows->pluck('transfer_pair_id'))
            ->where('amount', '>', 0)
            ->get()
            ->keyBy('transfer_pair_id');

        $transfers = $outflows->map(function (Transaction $out) use ($siblings) {
            $in = $siblings->get($out->transfer_pair_id);

            return [
                'id' => $out->id,
                'date' => $out->date->toDateString(),
                'from' => $out->account?->name,
                'to' => $in?->account?->name ?? '—',
                'amount' => Money::format(abs($out->amount)),
            ];
        });

        return Inertia::render('household/Transfers', [
            'transfers' => $transfers,
            'accounts' => $household->accounts()->where('archived', false)->orderBy('name')->get(['id', 'name']),
            'defaults' => [
                'date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(StoreTransferRequest $request, TransferFunds $action): RedirectResponse
    {
        $action->handle($request->validated(), $request->user(), $this->household($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer created.')]);

        return to_route('household.transfers.index');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $household = $this->household($request);

        abort_unless($transaction->household_id === $household->id && $transaction->transfer_pair_id, 403);

        $transaction->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer deleted.')]);

        return to_route('household.transfers.index');
    }
}
