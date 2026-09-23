<?php

it('opens the import page from the sidebar', function () {
    actingAsOwner();

    visit('/household/dashboard')
        ->click('Import')
        ->assertPathIs('/household/import')
        ->assertSee('Import statement')
        ->assertNoJavaScriptErrors();
});
