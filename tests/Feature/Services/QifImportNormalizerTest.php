<?php

use App\Services\Import\QifImportNormalizer;

test('it parses a QIF statement with Quicken apostrophe dates', function () {
    $qif = <<<'QIF'
        !Type:Bank
        D06/01'2026
        T-72.50
        PLOBLAWS #5025
        ^
        D06/02'2026
        T2,000.00
        PPAYROLL DEPOSIT
        MDirect deposit
        ^
        QIF;

    $result = (new QifImportNormalizer)->normalize($qif);

    expect($result->drafts)->toHaveCount(2)
        ->and($result->skipped)->toBe([]);

    expect($result->drafts[0]->date)->toBe('2026-06-01')
        ->and($result->drafts[0]->payee)->toBe('LOBLAWS #5025')
        ->and($result->drafts[0]->amountCents)->toBe(-7250)
        ->and($result->drafts[0]->externalId)->toBeNull();

    expect($result->drafts[1]->date)->toBe('2026-06-02')
        ->and($result->drafts[1]->amountCents)->toBe(200000);
});

test('it parses ISO and North-American slash dates', function () {
    $qif = <<<'QIF'
        !Type:Bank
        D2026-03-04
        T-1.00
        PISO
        ^
        D03/04/2026
        T-2.00
        PSlash
        ^
        QIF;

    $result = (new QifImportNormalizer)->normalize($qif);

    // Ambiguous slash dates resolve to North-American MM/DD/YYYY.
    expect($result->drafts[0]->date)->toBe('2026-03-04')
        ->and($result->drafts[1]->date)->toBe('2026-03-04');
});

test('it falls back to the memo when a record has no payee', function () {
    $qif = <<<'QIF'
        !Type:Bank
        D01/15/2026
        T-3.00
        MSERVICE CHARGE
        ^
        QIF;

    $result = (new QifImportNormalizer)->normalize($qif);

    expect($result->drafts[0]->payee)->toBe('SERVICE CHARGE');
});

test('it skips records missing a date or amount', function () {
    $qif = <<<'QIF'
        !Type:Bank
        T-5.00
        PNo date
        ^
        D01/20/2026
        PNo amount
        ^
        D01/21/2026
        T-6.00
        PGood
        ^
        QIF;

    $result = (new QifImportNormalizer)->normalize($qif);

    expect($result->drafts)->toHaveCount(1)
        ->and($result->drafts[0]->payee)->toBe('Good')
        ->and($result->skipped)->toHaveCount(2);
});
