<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Livewire\Livewire;

test('members page renders and shows the household owner', function () {
    $owner = User::factory()->create(['name' => 'Alice']);

    $this->actingAs($owner)
        ->get(route('household.members.index'))
        ->assertOk()
        ->assertSeeText('Alice');
});

test('inviting generates a HouseholdInvitation and surfaces the URL', function () {
    $owner = User::factory()->create();

    $component = Livewire::actingAs($owner)
        ->test('pages::household.members.index')
        ->set('form.email', 'newperson@example.com')
        ->set('form.role', 'member')
        ->call('invite')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('household_invitations', [
        'household_id' => $owner->household()->id,
        'email' => 'newperson@example.com',
        'invited_by' => $owner->id,
    ]);

    $invitation = HouseholdInvitation::firstWhere('email', 'newperson@example.com');
    $component->assertSet('lastInviteUrl', url('/register?invite='.$invitation->code));
});

test('cancel deletes the invitation', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::household.members.index')
        ->call('cancel', $invitation->id);

    expect(HouseholdInvitation::find($invitation->id))->toBeNull();
});

test('end-to-end: generate invite, register through it, become member of inviter household', function () {
    $owner = User::factory()->create();
    $ownerHousehold = $owner->household();

    Livewire::actingAs($owner)
        ->test('pages::household.members.index')
        ->set('form.email', 'invitee@example.com')
        ->set('form.role', 'member')
        ->call('invite');

    $invitation = HouseholdInvitation::firstWhere('email', 'invitee@example.com');

    $this->post(route('logout'));

    $this->get(route('register', ['invite' => $invitation->code]))->assertOk();

    $this->post(route('register.store'), [
        'name' => 'Bob Invitee',
        'email' => 'bob@example.com',
        'password' => 'password-secret',
        'password_confirmation' => 'password-secret',
    ])->assertSessionHasNoErrors();

    $bob = User::firstWhere('email', 'bob@example.com');
    expect($bob)->not->toBeNull()
        ->and($bob->household()->id)->toBe($ownerHousehold->id)
        ->and($bob->householdRole($ownerHousehold))->toBe(HouseholdRole::Member);

    $invitation->refresh();
    expect($invitation->accepted_at)->not->toBeNull();

    // Same URL can't be used again
    $this->post(route('logout'));
    $this->get(route('register', ['invite' => $invitation->code]))->assertForbidden();
});

test('guests are redirected from the members page', function () {
    $this->get(route('household.members.index'))
        ->assertRedirect(route('login'));
});
