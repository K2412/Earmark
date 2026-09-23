<?php

it('creates a scenario and shows reproducible endings', function () {
    actingAsOwner();

    visit('/household/scenarios')
        ->assertSee('Scenarios')
        ->click('@open-create-scenario')
        ->fill('sc-name', 'Retire at 60')
        ->fill('sc-start', '5000000')
        ->fill('sc-contrib', '1200000')
        ->click('@submit-scenario')
        ->assertSee('Retire at 60')
        ->assertSee('earmark.scenario.v1')
        ->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('scenarios', ['name' => 'Retire at 60']);
});
