<?php

use App\Models\Bucket;
use App\Services\Budget\BudgetService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Plan')] class extends Component {
    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
    }

    public function previousMonth(): void
    {
        $d = \Carbon\CarbonImmutable::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = $d->year;
        $this->month = $d->month;
    }

    public function nextMonth(): void
    {
        $d = \Carbon\CarbonImmutable::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = $d->year;
        $this->month = $d->month;
    }

    #[Computed]
    public function rows()
    {
        $service = app(BudgetService::class);
        $buckets = Bucket::query()
            ->where('archived', false)
            ->orderByRaw("CASE kind WHEN 'system' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $buckets->map(function (Bucket $b) use ($service) {
            $available = $service->availableForMonth($b, $this->year, $this->month);
            $obligation = $service->obligationForMonth($b, $this->year, $this->month);
            $rolled = $service->rolledForwardObligation($b, $this->year, $this->month);
            $needed = $obligation + $rolled;

            return [
                'bucket' => $b,
                'available' => $available,
                'obligation' => $obligation,
                'rolled' => $rolled,
                'needed' => $needed,
                'underfunded' => $available < $needed,
                'negative' => $available < 0,
            ];
        });
    }

    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows;

        return [
            'available' => $rows->sum('available'),
            'obligation' => $rows->sum('obligation'),
            'rolled' => $rows->sum('rolled'),
            'needed' => $rows->sum('needed'),
        ];
    }

    #[Computed]
    public function monthLabel(): string
    {
        return \Carbon\CarbonImmutable::createFromDate($this->year, $this->month, 1)->format('F Y');
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Plan') }}</flux:heading>
        <div class="flex items-center gap-2">
            <flux:button size="sm" wire:click="previousMonth">‹ {{ __('Prev') }}</flux:button>
            <flux:heading size="lg">{{ $this->monthLabel }}</flux:heading>
            <flux:button size="sm" wire:click="nextMonth">{{ __('Next') }} ›</flux:button>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Bucket') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Obligation') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Rolled-fwd') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Needed') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Available') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->rows as $row)
                <flux:table.row wire:key="plan-{{ $row['bucket']->id }}">
                    <flux:table.cell>{{ $row['bucket']->name }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($row['obligation'] / 100, 2) }}</flux:table.cell>
                    <flux:table.cell align="end">{{ number_format($row['rolled'] / 100, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-semibold">{{ number_format($row['needed'] / 100, 2) }}</flux:table.cell>
                    <flux:table.cell align="end" class="@if ($row['negative']) text-red-600 @elseif ($row['underfunded']) text-amber-600 @endif">
                        {{ number_format($row['available'] / 100, 2) }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($row['negative'])
                            <flux:badge color="red">{{ __('Negative') }}</flux:badge>
                        @elseif ($row['underfunded'])
                            <flux:badge color="amber">{{ __('Underfunded') }}</flux:badge>
                        @else
                            <flux:badge color="green">{{ __('OK') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 text-sm dark:border-neutral-700 dark:bg-zinc-900">
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <div class="text-zinc-500">{{ __('Total obligation') }}</div>
                <div class="font-mono">{{ number_format($this->totals['obligation'] / 100, 2) }}</div>
            </div>
            <div>
                <div class="text-zinc-500">{{ __('Total rolled-fwd') }}</div>
                <div class="font-mono">{{ number_format($this->totals['rolled'] / 100, 2) }}</div>
            </div>
            <div>
                <div class="text-zinc-500">{{ __('Total needed') }}</div>
                <div class="font-mono font-semibold">{{ number_format($this->totals['needed'] / 100, 2) }}</div>
            </div>
            <div>
                <div class="text-zinc-500">{{ __('Total available') }}</div>
                <div class="font-mono">{{ number_format($this->totals['available'] / 100, 2) }}</div>
            </div>
        </div>
    </div>
</div>
