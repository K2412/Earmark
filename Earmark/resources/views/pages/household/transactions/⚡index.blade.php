<?php

use App\Actions\Transactions\CreateTransaction;
use App\Livewire\Forms\TransactionForm;
use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Payee\PayeeRuleService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Transactions')] class extends Component {
    public TransactionForm $form;

    public bool $showCreateModal = false;

    public ?string $suggestedRuleId = null;

    public function mount(): void
    {
        $this->form->defaults();
    }

    /**
     * Fires when the payee field blurs (wire:model.live.blur). Looks up the
     * matching PayeeRule and prefills category + bucket — only if the user
     * hasn't already chosen one (so we don't overwrite their selections).
     */
    public function updatedFormPayee(string $value): void
    {
        $match = app(PayeeRuleService::class)->suggest($value);

        if ($match['rule'] === null) {
            $this->suggestedRuleId = null;

            return;
        }

        $this->suggestedRuleId = $match['rule']->id;

        if (empty($this->form->category_id) && $match['category_id']) {
            $this->form->category_id = $match['category_id'];
        }

        if (empty($this->form->bucket_id) && $match['bucket_id']) {
            $this->form->bucket_id = $match['bucket_id'];
        }
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::query()
            ->with(['account', 'category', 'bucket'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function accountOptions()
    {
        return Account::query()->where('archived', false)->orderBy('name')->get();
    }

    #[Computed]
    public function categoryOptions()
    {
        return Category::query()->where('archived', false)->orderBy('name')->get();
    }

    #[Computed]
    public function bucketOptions()
    {
        return Bucket::query()->where('archived', false)->orderBy('name')->get();
    }

    public function save(CreateTransaction $action): void
    {
        $this->form->validate();

        $action->handle($this->form->pull(), auth()->user());

        $this->form->defaults();
        $this->showCreateModal = false;
        $this->suggestedRuleId = null;
        unset($this->transactions);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showCreateModal', true)" data-test="open-create-transaction">
            {{ __('Add transaction') }}
        </flux:button>
    </div>

    @if ($this->transactions->isEmpty())
        <flux:callout icon="receipt-percent">
            <flux:callout.heading>{{ __('No transactions yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add one manually or import a PDF statement.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Account') }}</flux:table.column>
                <flux:table.column>{{ __('Payee') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Bucket') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->transactions as $transaction)
                    <flux:table.row wire:key="transaction-{{ $transaction->id }}">
                        <flux:table.cell>{{ $transaction->date->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->account?->name }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->payee }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->category?->name ?? ($transaction->is_split ? __('(split)') : '—') }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->bucket?->name ?? ($transaction->is_split ? __('(split)') : '—') }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">{{ number_format($transaction->amount / 100, 2) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showCreateModal" name="create-transaction-modal">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Add transaction') }}</flux:heading>

            <flux:input wire:model="form.date" :label="__('Date')" type="date" required />

            <flux:select wire:model="form.account_id" :label="__('Account')">
                <flux:select.option value="">{{ __('Select an account') }}</flux:select.option>
                @foreach ($this->accountOptions as $account)
                    <flux:select.option value="{{ $account->id }}">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.live.blur="form.payee" :label="__('Payee')" placeholder="Loblaws" required />
            @if ($suggestedRuleId)
                <flux:text size="sm" class="text-zinc-500">{{ __('Prefilled from payee rule (override below if needed).') }}</flux:text>
            @endif

            <flux:select wire:model="form.category_id" :label="__('Category')">
                <flux:select.option value="">{{ __('(none)') }}</flux:select.option>
                @foreach ($this->categoryOptions as $category)
                    <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.bucket_id" :label="__('Bucket')">
                <flux:select.option value="">{{ __('(none)') }}</flux:select.option>
                @foreach ($this->bucketOptions as $bucket)
                    <flux:select.option value="{{ $bucket->id }}">{{ $bucket->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="form.amount"
                :label="__('Amount (cents, negative for spend)')"
                type="number"
                step="1"
                required
            />

            <flux:input wire:model="form.memo" :label="__('Memo (optional)')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-create-transaction">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
