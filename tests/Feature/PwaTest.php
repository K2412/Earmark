<?php

test('the web app manifest is valid and installable', function () {
    $path = public_path('manifest.webmanifest');

    expect($path)->toBeFile();

    $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Earmark')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/household/dashboard')
        ->and($manifest['icons'])->not->toBeEmpty();
});

test('the service worker and offline fallback ship in public', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile();
});

test('the service worker never caches financial data', function () {
    $worker = (string) file_get_contents(public_path('sw.js'));

    // The cache must only ever hold the offline shell, never household data.
    expect($worker)->toContain('offline.html')
        ->and($worker)->not->toContain('/household/');
});

test('the app shell registers the PWA in its head', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('/manifest.webmanifest', false)
        ->assertSee("serviceWorker.register('/sw.js')", false)
        ->assertSee('name="theme-color"', false);
});
