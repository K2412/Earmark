<?php

use App\Actions\Accounts\CreateAccount;
use App\Livewire\Forms\AccountForm;
use App\Models\Account;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Accounts')] class extends Component {
    public AccountForm $form;

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->form->defaults();
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()
            ->where('archived', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function save(CreateAccount $action): void
    {
        $this->form->validate();

        $action->handle($this->form->pull());

        $this->form->defaults();
        $this->showCreateModal = false;
        unset($this->accounts);

        $this->dispatch('account-created');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Accounts') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showCreateModal', true)" data-test="open-create-account">
            {{ __('Add account') }}
        </flux:button>
    </div>

    @if ($this->accounts->isEmpty())
        <flux:callout icon="banknotes">
            <flux:callout.heading>{{ __('No accounts yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add your first chequing, savings, or credit card account.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Starting balance') }}</flux:table.column>
                <flux:table.column>{{ __('Starting date') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->accounts as $account)
                    <flux:table.row wire:key="account-{{ $account->id }}">
                        <flux:table.cell>{{ $account->name }}</flux:table.cell>
                        <flux:table.cell>{{ str_replace('_', ' ', $account->type) }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($account->starting_balance / 100, 2) }}</flux:table.cell>
                        <flux:table.cell>{{ $account->starting_balance_date->format('Y-m-d') }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showCreateModal" name="create-account-modal">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Add account') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" placeholder="Chequing" required />

            <flux:select wire:model="form.type" :label="__('Type')">
                <flux:select.option value="chequing">{{ __('Chequing') }}</flux:select.option>
                <flux:select.option value="savings">{{ __('Savings') }}</flux:select.option>
                <flux:select.option value="credit_card">{{ __('Credit card') }}</flux:select.option>
                <flux:select.option value="cash">{{ __('Cash') }}</flux:select.option>
                <flux:select.option value="investment">{{ __('Investment') }}</flux:select.option>
                <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
            </flux:select>

            <flux:input
                wire:model="form.starting_balance"
                :label="__('Starting balance (cents)')"
                type="number"
                step="1"
                placeholder="0"
            />

            <flux:input
                wire:model="form.starting_balance_date"
                :label="__('Starting balance date')"
                type="date"
                required
            />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-create-account">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
