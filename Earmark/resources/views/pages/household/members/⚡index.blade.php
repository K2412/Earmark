<?php

use App\Actions\Households\CreateInvitation;
use App\Livewire\Forms\InvitationForm;
use App\Models\HouseholdInvitation;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Household members')] class extends Component {
    public InvitationForm $form;

    public bool $showInviteModal = false;

    public ?string $lastInviteUrl = null;

    public function mount(): void
    {
        $this->form->defaults();
    }

    #[Computed]
    public function household()
    {
        return auth()->user()->household();
    }

    #[Computed]
    public function members()
    {
        return $this->household?->members()->orderBy('name')->get() ?? collect();
    }

    #[Computed]
    public function pendingInvitations()
    {
        return $this->household?->invitations()
            ->whereNull('accepted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
            ->orderBy('created_at', 'desc')
            ->get() ?? collect();
    }

    public function invite(CreateInvitation $action): void
    {
        $this->form->validate();

        abort_if(! $this->household, 403);

        $invitation = $action->handle($this->form->pull(), $this->household, auth()->user());

        $this->lastInviteUrl = url('/register?invite='.$invitation->code);
        $this->form->defaults();
        $this->showInviteModal = false;
        unset($this->pendingInvitations);
    }

    public function cancel(string $invitationId): void
    {
        $invitation = HouseholdInvitation::findOrFail($invitationId);

        abort_if($invitation->household_id !== $this->household?->id, 403);

        $invitation->delete();
        unset($this->pendingInvitations);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Household members') }}</flux:heading>
        <flux:button variant="primary" wire:click="$set('showInviteModal', true)" data-test="open-invite-modal">
            {{ __('Invite member') }}
        </flux:button>
    </div>

    @if ($lastInviteUrl)
        <flux:callout variant="success" icon="link">
            <flux:callout.heading>{{ __('Invite link generated') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Copy and send this URL to the invitee. It expires in 14 days and can only be used once.') }}</flux:callout.text>
            <div class="mt-2 flex items-center gap-2">
                <flux:input :value="$lastInviteUrl" readonly class="flex-1 font-mono text-sm" />
                <flux:button
                    size="sm"
                    x-data
                    x-on:click="navigator.clipboard.writeText('{{ $lastInviteUrl }}'); $flux.toast.success('Copied')"
                >
                    {{ __('Copy') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <section class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Members') }}</flux:heading>
        @if ($this->members->isEmpty())
            <flux:text>{{ __('No members yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->members as $member)
                        <flux:table.row wire:key="member-{{ $member->id }}">
                            <flux:table.cell>{{ $member->name }}</flux:table.cell>
                            <flux:table.cell>{{ $member->email }}</flux:table.cell>
                            <flux:table.cell>{{ $member->pivot->role }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <section class="flex flex-col gap-2">
        <flux:heading size="lg">{{ __('Pending invitations') }}</flux:heading>
        @if ($this->pendingInvitations->isEmpty())
            <flux:text>{{ __('No pending invitations.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column>{{ __('URL') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->pendingInvitations as $invitation)
                        <flux:table.row wire:key="invitation-{{ $invitation->id }}">
                            <flux:table.cell>{{ $invitation->email }}</flux:table.cell>
                            <flux:table.cell>{{ $invitation->role->value }}</flux:table.cell>
                            <flux:table.cell>{{ $invitation->expires_at?->diffForHumans() ?? __('never') }}</flux:table.cell>
                            <flux:table.cell class="font-mono text-xs">/register?invite={{ Str::limit($invitation->code, 12) }}…</flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    variant="danger"
                                    size="sm"
                                    wire:click="cancel('{{ $invitation->id }}')"
                                    wire:confirm="{{ __('Cancel this invitation?') }}"
                                    data-test="cancel-invitation-{{ $invitation->id }}"
                                >
                                    {{ __('Cancel') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <flux:modal wire:model="showInviteModal" name="invite-member-modal">
        <form wire:submit="invite" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Invite a household member') }}</flux:heading>

            <flux:input wire:model="form.email" :label="__('Email')" type="email" required />

            <flux:select wire:model="form.role" :label="__('Role')">
                <flux:select.option value="member">{{ __('Member') }}</flux:select.option>
                <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="$set('showInviteModal', false)" type="button">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" type="submit" data-test="submit-invite">
                    {{ __('Generate link') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
