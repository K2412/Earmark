<?php

use App\Actions\Transactions\TransferFunds;
use App\Livewire\Forms\TransferForm;
use App\Models\Account;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Transfers')] class extends Component {
    public TransferForm $form;

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->form->defaults();
    }

    #[Computed]
    public function accountOptions()
    {
        return Account::query()->where('archived', false)->orderBy('name')->get();
    }

    #[Computed]
    public function transfers()
    {
        return Transaction::query()
            ->with('account')
            ->whereNotNull('transfer_pair_id')
            ->where('amount', '<', 0)
            ->orderBy('date', 'desc')
            ->limit(50)
            ->get();
    }

    public function save(TransferFunds $action): void
    {
        $this->form->validate();

        try {
            $action->handle($this->form->pull(), auth()->user());
        } catch (\InvalidArgumentException $e) {
            $this->addError('form.amount', $e->getMessage());

            return;
        }

        $this->form->defaults();
        $this->showCreateModal = false;
        unset($this->transfers);
    }

    public function destroyTransfer(string $transactionId): void
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $transaction->transfer_pair_id) {
            return;
        }

        $transaction->delete(); // model deleting hook cleans the sibling
        unset($this->transfers);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Transfers') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showCreateModal', true)" data-test="open-create-transfer">
            {{ __('New transfer') }}
        </flux:button>
    </div>

    @if ($this->transfers->isEmpty())
        <flux:callout icon="arrows-right-left">
            <flux:callout.heading>{{ __('No transfers yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Use this to move money between accounts without affecting category budgets.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('From') }}</flux:table.column>
                <flux:table.column>{{ __('To') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->transfers as $out)
                    @php($in = \App\Models\Transaction::where('transfer_pair_id', $out->transfer_pair_id)->where('id', '!=', $out->id)->first())
                    <flux:table.row wire:key="transfer-{{ $out->id }}">
                        <flux:table.cell>{{ $out->date->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ $out->account?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $in?->account?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">{{ number_format(abs($out->amount) / 100, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button
                                variant="danger"
                                size="sm"
                                wire:click="destroyTransfer('{{ $out->id }}')"
                                wire:confirm="{{ __('Delete this transfer (both sides)?') }}"
                                data-test="destroy-transfer-{{ $out->id }}"
                            >
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showCreateModal" name="create-transfer-modal">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Transfer funds') }}</flux:heading>

            <flux:input wire:model="form.date" :label="__('Date')" type="date" required />

            <flux:select wire:model="form.from_account_id" :label="__('From account')">
                <flux:select.option value="">{{ __('Select source') }}</flux:select.option>
                @foreach ($this->accountOptions as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.to_account_id" :label="__('To account')">
                <flux:select.option value="">{{ __('Select destination') }}</flux:select.option>
                @foreach ($this->accountOptions as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.amount" :label="__('Amount (cents)')" type="number" step="1" min="1" required />
            <flux:input wire:model="form.memo" :label="__('Memo (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-create-transfer">
                    {{ __('Transfer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
