<?php

use App\Enums\HouseholdRole;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members page renders and shows the household owner', function () {
    $owner = User::factory()->create(['name' => 'Alice']);

    $this->actingAs($owner)
        ->get(route('household.members.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('household/Members')
            ->has('members', 1)
            ->where('members.0.name', 'Alice')
        );
});

test('inviting generates a HouseholdInvitation and surfaces the URL', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('household.members.invitations.store'), [
            'email' => 'newperson@example.com',
            'role' => 'member',
        ])
        ->assertRedirect(route('household.members.index'));

    $this->assertDatabaseHas('household_invitations', [
        'household_id' => $owner->household()->id,
        'email' => 'newperson@example.com',
        'invited_by' => $owner->id,
    ]);

    $invitation = HouseholdInvitation::firstWhere('email', 'newperson@example.com');

    $this->actingAs($owner)
        ->get(route('household.members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inviteUrl', url('/register?invite='.$invitation->code))
        );
});

test('cancel deletes the invitation', function () {
    $owner = User::factory()->create();
    $invitation = HouseholdInvitation::factory()->create([
        'household_id' => $owner->household()->id,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->delete(route('household.members.invitations.destroy', $invitation->id))
        ->assertRedirect(route('household.members.index'));

    expect(HouseholdInvitation::find($invitation->id))->toBeNull();
});

test('end-to-end: generate invite, register through it, become member of inviter household', function () {
    $owner = User::factory()->create();
    $ownerHousehold = $owner->household();

    $this->actingAs($owner)
        ->post(route('household.members.invitations.store'), [
            'email' => 'invitee@example.com',
            'role' => 'member',
        ]);

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

    $this->post(route('logout'));
    $this->get(route('register', ['invite' => $invitation->code]))->assertForbidden();
});

test('guests are redirected from the members page', function () {
    $this->get(route('household.members.index'))
        ->assertRedirect(route('login'));
});
