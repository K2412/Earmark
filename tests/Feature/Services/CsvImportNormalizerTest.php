<?php

use App\Services\Import\CsvImportNormalizer;

beforeEach(function () {
    $this->normalizer = new CsvImportNormalizer;
});

function singleMapping(string $format = 'YYYY-MM-DD', string $sign = 'negative_is_outflow'): array
{
    return ['date_format' => $format, 'amount_mode' => 'single', 'sign' => $sign];
}

test('it parses textual month dates like an Amex export (DD Mon YYYY, positive is spending)', function () {
    $result = $this->normalizer->normalize([
        ['date' => '22 Sep 2026', 'payee' => 'BRILLIANT.ORG - EDU', 'amount' => '45.20'],
        ['date' => '22 Sep 2026', 'payee' => 'PAYMENT RECEIVED - THANK YOU', 'amount' => '-982.00'],
    ], singleMapping('DD Mon YYYY', 'positive_is_outflow'));

    expect($result->drafts)->toHaveCount(2)
        ->and($result->skipped)->toBe([]);

    // On Amex a purchase is a positive amount → spending (negative cents).
    expect($result->drafts[0]->date)->toBe('2026-09-22')
        ->and($result->drafts[0]->amountCents)->toBe(-4520);

    // A payment received is negative on Amex → money in (positive cents).
    expect($result->drafts[1]->amountCents)->toBe(98200);
});

test('normalizes a single-amount row to iso date and signed cents', function () {
    $result = $this->normalizer->normalize(
        [['date' => '2026-06-01', 'payee' => '  Loblaws  #5025 ', 'amount' => '-72.50']],
        singleMapping(),
    );

    expect($result->skipped)->toBe([])
        ->and($result->drafts)->toHaveCount(1);

    $draft = $result->drafts[0];

    expect($draft->date)->toBe('2026-06-01')
        ->and($draft->amountCents)->toBe(-7250)
        ->and($draft->payee)->toBe('Loblaws #5025')
        ->and($draft->rawPayee)->toBe('Loblaws  #5025');
});

test('respects the positive-is-outflow sign convention', function () {
    $result = $this->normalizer->normalize(
        [['date' => '2026-06-01', 'payee' => 'Rent', 'amount' => '1500.00']],
        singleMapping(sign: 'positive_is_outflow'),
    );

    expect($result->drafts[0]->amountCents)->toBe(-150000);
});

test('maps debit and credit columns to outflow and inflow', function () {
    $result = $this->normalizer->normalize(
        [
            ['date' => '2026-06-01', 'payee' => 'Groceries', 'debit' => '72.50', 'credit' => ''],
            ['date' => '2026-06-02', 'payee' => 'Payroll', 'debit' => '', 'credit' => '2,000.00'],
        ],
        ['date_format' => 'YYYY-MM-DD', 'amount_mode' => 'debit_credit'],
    );

    expect($result->drafts[0]->amountCents)->toBe(-7250)
        ->and($result->drafts[1]->amountCents)->toBe(200000);
});

test('parses accounting-style negatives and thousands separators', function () {
    $result = $this->normalizer->normalize(
        [['date' => '2026-06-01', 'payee' => 'Refund', 'amount' => '($1,234.56)']],
        singleMapping(),
    );

    expect($result->drafts[0]->amountCents)->toBe(-123456);
});

test('skips rows with an unrecognized date without discarding them silently', function () {
    $result = $this->normalizer->normalize(
        [
            ['date' => 'not-a-date', 'payee' => 'X', 'amount' => '-10.00'],
            ['date' => '2026-06-01', 'payee' => 'Y', 'amount' => '-20.00'],
        ],
        singleMapping(),
    );

    expect($result->drafts)->toHaveCount(1)
        ->and($result->skipped)->toBe([['row' => 1, 'reason' => 'Unrecognized date.']]);
});

test('skips rows with no parseable amount', function () {
    $result = $this->normalizer->normalize(
        [['date' => '2026-06-01', 'payee' => 'X', 'amount' => '']],
        singleMapping(),
    );

    expect($result->drafts)->toBe([])
        ->and($result->skipped)->toBe([['row' => 1, 'reason' => 'No parseable amount.']]);
});

test('flags a missing description with a warning', function () {
    $result = $this->normalizer->normalize(
        [['date' => '2026-06-01', 'payee' => '', 'amount' => '-5.00']],
        singleMapping(),
    );

    expect($result->drafts[0]->payee)->toBe(CsvImportNormalizer::NO_DESCRIPTION)
        ->and($result->drafts[0]->warnings)->toBe(['Missing description.']);
});

test('parses each supported date format', function () {
    $cases = [
        ['MM/DD/YYYY', '06/01/2026'],
        ['DD/MM/YYYY', '01/06/2026'],
        ['YYYY/MM/DD', '2026/06/01'],
        ['DD-MM-YYYY', '01-06-2026'],
    ];

    foreach ($cases as [$format, $raw]) {
        $result = $this->normalizer->normalize(
            [['date' => $raw, 'payee' => 'X', 'amount' => '-1.00']],
            singleMapping(format: $format),
        );

        expect($result->drafts[0]->date)->toBe('2026-06-01');
    }
});
