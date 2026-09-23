<?php

/**
 * Repository-distribution guards for the open-source and data-ownership
 * boundary (planning task #1244): the licence is present and detectable, the
 * governance docs exist, and the privacy default is stated.
 */
function distributionFile(string $relativePath): string
{
    $path = base_path($relativePath);

    expect(file_exists($path))->toBeTrue("Expected {$relativePath} to exist at the repository root.");

    return (string) file_get_contents($path);
}

test('a root MIT licence exists and matches the package metadata', function () {
    $license = distributionFile('LICENSE');

    expect($license)
        ->toContain('MIT License')
        ->toContain('Copyright (c)')
        ->toContain('WITHOUT WARRANTY OF ANY KIND');

    $composer = json_decode(distributionFile('composer.json'), true);

    expect($composer['license'])->toBe('MIT');
});

test('contribution docs cover setup, the security route, and support boundaries', function () {
    $contributing = distributionFile('CONTRIBUTING.md');

    expect($contributing)
        ->toContain('composer setup')
        ->toContain('SECURITY.md')
        ->toContain('best-effort')
        ->toContain('do **not** promise');
});

test('the security policy gives a private disclosure route', function () {
    $security = distributionFile('SECURITY.md');

    expect($security)
        ->toContain('vulnerabilit')
        ->toContain('privately');
});

test('the docs state financial data is not sent to Earmark or third parties by default', function () {
    expect(distributionFile('README.md'))->toContain('third-party service');

    expect(distributionFile('SECURITY.md'))
        ->toContain('does **not** send your financial data')
        ->toContain('By default');
});
