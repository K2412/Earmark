<?php

it('shows the data, backup, and deletion controls', function () {
    actingAsOwner();

    visit('/household/portability')
        ->assertSee('Data & backup')
        ->assertSee('Export backup')
        ->assertSee('Delete all financial data')
        ->assertNoJavaScriptErrors();
});
