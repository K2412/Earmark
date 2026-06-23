<?php

use App\Models\Bucket;
use App\Services\Budget\BudgetService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function underfundedBuckets()
    {
        return app(BudgetService::class)->underfundedBuckets(now()->year, now()->month);
    }

    #[Computed]
    public function unassignedAvailable(): int
    {
        $unassigned = Bucket::firstWhere('name', Bucket::UNASSIGNED_FUNDS);

        if (! $unassigned) {
            return 0;
        }

        return app(BudgetService::class)->availableForMonth($unassigned, now()->year, now()->month);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="sm" class="text-zinc-500">{{ __('Unassigned Funds') }}</flux:heading>
            <div class="mt-2 text-2xl font-semibold @if ($this->unassignedAvailable < 0) text-red-600 @endif">
                ${{ number_format($this->unassignedAvailable / 100, 2) }}
            </div>
            <flux:text size="sm" class="text-zinc-500">{{ __('Available to assign') }}</flux:text>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900 md:col-span-2">
            <div class="flex items-center justify-between">
                <flux:heading size="sm" class="text-zinc-500">{{ __('Underfunded buckets') }}</flux:heading>
                <flux:badge color="{{ $this->underfundedBuckets->count() > 0 ? 'amber' : 'green' }}">
                    {{ $this->underfundedBuckets->count() }}
                </flux:badge>
            </div>
            @if ($this->underfundedBuckets->isEmpty())
                <flux:text size="sm" class="mt-3 text-zinc-500">{{ __('Every bucket has enough for this month and any rolled-forward gaps. Nice.') }}</flux:text>
            @else
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($this->underfundedBuckets as $bucket)
                        <li class="flex items-center justify-between" wire:key="under-{{ $bucket->id }}">
                            <span>{{ $bucket->name }}</span>
                            <flux:link :href="route('household.buckets.index')" wire:navigate class="text-amber-600">
                                {{ __('Fund') }}
                            </flux:link>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('household.accounts.index') }}" wire:navigate class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-400 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="sm">{{ __('Accounts') }}</flux:heading>
        </a>
        <a href="{{ route('household.categories.index') }}" wire:navigate class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-400 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="sm">{{ __('Categories') }}</flux:heading>
        </a>
        <a href="{{ route('household.buckets.index') }}" wire:navigate class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-400 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="sm">{{ __('Buckets') }}</flux:heading>
        </a>
        <a href="{{ route('household.transactions.index') }}" wire:navigate class="rounded-xl border border-neutral-200 bg-white p-4 transition hover:border-neutral-400 dark:border-neutral-700 dark:bg-zinc-900">
            <flux:heading size="sm">{{ __('Transactions') }}</flux:heading>
        </a>
    </div>
</div>
