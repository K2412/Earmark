<?php

it('shows the assumption catalogue and can override a value', function () {
    actingAsOwner();

    visit('/household/assumptions')
        ->assertSee('Planning assumptions')
        ->assertSee('tfsa limit')
        ->assertSee('System')
        ->click('@open-override')
        ->fill('a-value', '750000')
        ->click('@submit-override')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('assumptions', ['key' => 'tfsa_limit', 'value' => 750000]);
});
