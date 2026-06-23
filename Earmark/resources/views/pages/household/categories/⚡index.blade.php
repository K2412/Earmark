<?php

use App\Actions\Categories\CreateCategory;
use App\Livewire\Forms\CategoryForm;
use App\Models\Category;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component {
    public CategoryForm $form;

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->form->defaults();
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->where('archived', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function save(CreateCategory $action): void
    {
        $this->form->validate();

        $action->handle($this->form->pull());

        $this->form->defaults();
        $this->showCreateModal = false;
        unset($this->categories);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showCreateModal', true)" data-test="open-create-category">
            {{ __('Add category') }}
        </flux:button>
    </div>

    @if ($this->categories->isEmpty())
        <flux:callout icon="tag">
            <flux:callout.heading>{{ __('No categories yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add a category to start tagging transactions.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->categories as $category)
                    <flux:table.row wire:key="category-{{ $category->id }}">
                        <flux:table.cell>{{ $category->name }}</flux:table.cell>
                        <flux:table.cell>{{ $category->type }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showCreateModal" name="create-category-modal">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Add category') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" placeholder="Groceries" required />

            <flux:select wire:model="form.type" :label="__('Type')">
                <flux:select.option value="income">{{ __('Income') }}</flux:select.option>
                <flux:select.option value="housing">{{ __('Housing') }}</flux:select.option>
                <flux:select.option value="transportation">{{ __('Transportation') }}</flux:select.option>
                <flux:select.option value="food">{{ __('Food') }}</flux:select.option>
                <flux:select.option value="household">{{ __('Household') }}</flux:select.option>
                <flux:select.option value="personal">{{ __('Personal') }}</flux:select.option>
                <flux:select.option value="health">{{ __('Health') }}</flux:select.option>
                <flux:select.option value="debt">{{ __('Debt') }}</flux:select.option>
                <flux:select.option value="savings">{{ __('Savings') }}</flux:select.option>
                <flux:select.option value="fees">{{ __('Fees') }}</flux:select.option>
                <flux:select.option value="other">{{ __('Other') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-create-category">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
