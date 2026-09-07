<?php

use App\Models\HouseholdInvitation;

it('generates a member invite and cancels it', function () {
    actingAsOwner(['name' => 'Alice Owner']);

    $page = visit('/household/members');

    $page->assertSee('Alice Owner')
        ->click('@open-invite-modal')
        ->fill('email', 'roommate@example.com')
        ->select('role', 'member')
        ->click('@submit-invite')
        ->assertSee('Invite link generated')
        ->assertSee('roommate@example.com')
        ->assertSee('Member')
        ->assertScript(
            'document.querySelector("[data-test=invite-url] input")?.value.includes("register?invite=")',
        );

    $invitation = HouseholdInvitation::query()
        ->where('email', 'roommate@example.com')
        ->sole();

    $page->click('@cancel-invitation-'.$invitation->id)
        ->assertSee('No pending invitations.');

    $this->assertDatabaseMissing('household_invitations', [
        'id' => $invitation->id,
    ]);
});

it('generates an admin invite', function () {
    actingAsOwner(['name' => 'Alice Owner']);

    visit('/household/members')
        ->click('@open-invite-modal')
        ->fill('email', 'coowner@example.com')
        ->select('role', 'admin')
        ->click('@submit-invite')
        ->assertSee('coowner@example.com')
        ->assertSee('Admin');

    $this->assertDatabaseHas('household_invitations', [
        'email' => 'coowner@example.com',
        'role' => 'admin',
    ]);
});
