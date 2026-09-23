<?php

it('adds an investment holding manually', function () {
    actingAsOwner();

    visit('/household/holdings')
        ->assertSee('Investments')
        ->click('@open-create-holding')
        ->fill('h-name', 'VEQT')
        ->fill('h-cost', '1000000')
        ->fill('h-market', '1120000')
        ->click('@submit-holding')
        ->assertSee('VEQT')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('holdings', ['name' => 'VEQT', 'market_value_cents' => 1120000]);
});
