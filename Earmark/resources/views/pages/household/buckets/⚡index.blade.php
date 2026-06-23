<?php

use App\Actions\Buckets\CreateBucket;
use App\Livewire\Forms\BucketForm;
use App\Models\Bucket;
use App\Services\Budget\BudgetService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Buckets')] class extends Component {
    public BucketForm $form;

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->form->defaults();
    }

    #[Computed]
    public function buckets()
    {
        return Bucket::query()
            ->where('archived', false)
            ->orderByRaw("CASE kind WHEN 'system' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Per-bucket envelope status keyed by bucket id:
     *   ['available' => int, 'needed' => int, 'underfunded' => bool, 'negative' => bool]
     */
    #[Computed]
    public function status()
    {
        $service = app(BudgetService::class);
        $year = now()->year;
        $month = now()->month;

        return $this->buckets->mapWithKeys(function (Bucket $bucket) use ($service, $year, $month) {
            $available = $service->availableForMonth($bucket, $year, $month);
            $needed = $service->obligationForMonth($bucket, $year, $month)
                + $service->rolledForwardObligation($bucket, $year, $month);

            return [$bucket->id => [
                'available' => $available,
                'needed' => $needed,
                'underfunded' => $available < $needed,
                'negative' => $available < 0,
            ]];
        });
    }

    public function save(CreateBucket $action): void
    {
        $this->form->validate();

        $action->handle($this->form->pull(), auth()->user());

        $this->form->defaults();
        $this->showCreateModal = false;
        unset($this->buckets);
    }

    public function destroyBucket(string $bucketId): void
    {
        $bucket = Bucket::findOrFail($bucketId);

        if ($bucket->isProtected()) {
            $this->addError('protected', __('System buckets cannot be deleted.'));

            return;
        }

        $bucket->delete();
        unset($this->buckets);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Buckets') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showCreateModal', true)" data-test="open-create-bucket">
            {{ __('Add bucket') }}
        </flux:button>
    </div>

    @error('protected')
        <flux:callout variant="warning" icon="lock-closed">
            <flux:callout.text>{{ $message }}</flux:callout.text>
        </flux:callout>
    @enderror

    @if ($this->buckets->isEmpty())
        <flux:callout icon="archive-box">
            <flux:callout.heading>{{ __('No buckets yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Buckets hold money for upcoming bills and goals.') }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Kind') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Monthly obligation') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Available') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Target') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->buckets as $bucket)
                    <flux:table.row wire:key="bucket-{{ $bucket->id }}">
                        <flux:table.cell>
                            {{ $bucket->name }}
                            @if ($bucket->isProtected())
                                <flux:badge size="sm" color="zinc">{{ __('System') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $bucket->kind }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($bucket->monthly_obligation / 100, 2) }}</flux:table.cell>
                        @php($s = $this->status[$bucket->id] ?? null)
                        <flux:table.cell align="end" class="@if ($s && $s['negative']) text-red-600 font-semibold @elseif ($s && $s['underfunded']) text-amber-600 @endif">
                            {{ $s ? number_format($s['available'] / 100, 2) : '—' }}
                            @if ($s && $s['negative'])
                                <flux:badge size="sm" color="red">{{ __('Negative') }}</flux:badge>
                            @elseif ($s && $s['underfunded'])
                                <flux:badge size="sm" color="amber">{{ __('Underfunded') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            {{ $bucket->target_amount !== null ? number_format($bucket->target_amount / 100, 2) : '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @unless ($bucket->isProtected())
                                <flux:button
                                    variant="danger"
                                    size="sm"
                                    wire:click="destroyBucket('{{ $bucket->id }}')"
                                    wire:confirm="{{ __('Delete this bucket? This cannot be undone.') }}"
                                    data-test="destroy-bucket-{{ $bucket->id }}"
                                >
                                    {{ __('Delete') }}
                                </flux:button>
                            @endunless
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model="showCreateModal" name="create-bucket-modal">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Add bucket') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" placeholder="Rent" required />

            <flux:select wire:model="form.kind" :label="__('Kind')">
                <flux:select.option value="ongoing">{{ __('Ongoing (monthly)') }}</flux:select.option>
                <flux:select.option value="goal">{{ __('Goal (one-time)') }}</flux:select.option>
            </flux:select>

            <flux:input wire:model="form.monthly_obligation" :label="__('Monthly obligation (cents)')" type="number" step="1" />
            <flux:input wire:model="form.target_amount" :label="__('Target amount (cents, optional)')" type="number" step="1" />
            <flux:input wire:model="form.target_date" :label="__('Target date')" type="date" required />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showCreateModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-create-bucket">
                    {{ __('Create') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
