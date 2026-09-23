<?php

use App\Services\Import\OfxImportNormalizer;

function ofxSgml(): string
{
    return <<<'OFX'
        OFXHEADER:100
        DATA:OFXSGML
        VERSION:102

        <OFX>
        <BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
        <STMTTRN>
        <TRNTYPE>DEBIT
        <DTPOSTED>20260601120000[-5:EST]
        <TRNAMT>-72.50
        <FITID>2026060101
        <NAME>LOBLAWS #5025
        </STMTTRN>
        <STMTTRN>
        <TRNTYPE>CREDIT
        <DTPOSTED>20260602
        <TRNAMT>2000.00
        <FITID>2026060202
        <NAME>PAYROLL DEPOSIT
        </STMTTRN>
        </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1>
        </OFX>
        OFX;
}

test('it parses an OFX 1.x SGML statement into draft rows', function () {
    $result = (new OfxImportNormalizer)->normalize(ofxSgml());

    expect($result->drafts)->toHaveCount(2)
        ->and($result->skipped)->toBe([]);

    $debit = $result->drafts[0];
    expect($debit->date)->toBe('2026-06-01')
        ->and($debit->payee)->toBe('LOBLAWS #5025')
        ->and($debit->amountCents)->toBe(-7250)
        ->and($debit->externalId)->toBe('2026060101');

    $credit = $result->drafts[1];
    expect($credit->date)->toBe('2026-06-02')
        ->and($credit->amountCents)->toBe(200000)
        ->and($credit->externalId)->toBe('2026060202');
});

test('it parses an OFX 2.x XML statement and prefers NAME over MEMO', function () {
    $xml = <<<'OFX'
        <?xml version="1.0" encoding="UTF-8"?>
        <OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKTRANLIST>
        <STMTTRN><TRNTYPE>DEBIT</TRNTYPE><DTPOSTED>20260701</DTPOSTED><TRNAMT>-15.00</TRNAMT><FITID>x1</FITID><NAME>Coffee</NAME><MEMO>Latte</MEMO></STMTTRN>
        </BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>
        OFX;

    $result = (new OfxImportNormalizer)->normalize($xml);

    expect($result->drafts)->toHaveCount(1);
    expect($result->drafts[0]->date)->toBe('2026-07-01')
        ->and($result->drafts[0]->payee)->toBe('Coffee')
        ->and($result->drafts[0]->amountCents)->toBe(-1500)
        ->and($result->drafts[0]->externalId)->toBe('x1');
});

test('it falls back to MEMO when a transaction has no NAME', function () {
    $ofx = <<<'OFX'
        <OFX><BANKTRANLIST>
        <STMTTRN><DTPOSTED>20260801<TRNAMT>-9.99<FITID>m1<MEMO>INTERAC PURCHASE</STMTTRN>
        </BANKTRANLIST></OFX>
        OFX;

    $result = (new OfxImportNormalizer)->normalize($ofx);

    expect($result->drafts[0]->payee)->toBe('INTERAC PURCHASE');
});

test('it skips transactions with an unreadable date or amount', function () {
    $ofx = <<<'OFX'
        <OFX><BANKTRANLIST>
        <STMTTRN><DTPOSTED>notadate<TRNAMT>-1.00<FITID>a<NAME>Bad date</STMTTRN>
        <STMTTRN><DTPOSTED>20260901<FITID>b<NAME>No amount</STMTTRN>
        <STMTTRN><DTPOSTED>20260902<TRNAMT>-5.00<FITID>c<NAME>Good</STMTTRN>
        </BANKTRANLIST></OFX>
        OFX;

    $result = (new OfxImportNormalizer)->normalize($ofx);

    expect($result->drafts)->toHaveCount(1)
        ->and($result->drafts[0]->payee)->toBe('Good')
        ->and($result->skipped)->toHaveCount(2);
});

test('it returns no drafts for a file with no transactions', function () {
    $result = (new OfxImportNormalizer)->normalize('this is not an ofx file');

    expect($result->drafts)->toBe([]);
});
