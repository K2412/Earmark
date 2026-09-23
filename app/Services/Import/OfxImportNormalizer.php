<?php

namespace App\Services\Import;

use App\Support\Import\DraftRow;
use App\Support\Import\NormalizationResult;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Normalizes OFX-family bank exports — OFX 1.x (SGML), OFX 2.x (XML), and the Intuit
 * QFX/QBO variants — into the shared draft-row contract (#1269). These formats are
 * self-describing, so no column mapping is needed. Each <STMTTRN> carries a
 * bank-assigned <FITID> that becomes the draft's external id for exact duplicate
 * detection. All money math stays on the server via Money::parseToCents.
 */
class OfxImportNormalizer
{
    public const PARSER_VERSION = 'earmark.ofx.v1';

    public function normalize(string $content): NormalizationResult
    {
        $drafts = [];
        $skipped = [];
        $index = 0;

        foreach ($this->transactionBlocks($content) as $block) {
            $index++;

            $rawDate = $this->tag($block, 'DTPOSTED');
            $rawAmount = $this->tag($block, 'TRNAMT');
            $date = $rawDate !== null ? $this->normalizeDate($rawDate) : null;
            $amount = $rawAmount !== null ? Money::parseToCents($rawAmount) : null;

            if ($date === null) {
                $skipped[] = ['row' => $index, 'reason' => 'Missing or unreadable posted date.'];

                continue;
            }

            if ($amount === null) {
                $skipped[] = ['row' => $index, 'reason' => 'Missing or unreadable amount.'];

                continue;
            }

            $rawPayee = $this->tag($block, 'NAME')
                ?? $this->tag($block, 'PAYEE')
                ?? $this->tag($block, 'MEMO')
                ?? '';
            $payee = trim((string) preg_replace('/\s+/', ' ', $rawPayee));
            $fitid = $this->tag($block, 'FITID');

            $drafts[] = new DraftRow(
                date: $date,
                payee: $payee,
                rawPayee: $rawPayee,
                amountCents: $amount,
                warnings: $payee === '' ? ['Missing description.'] : [],
                externalId: ($fitid !== null && $fitid !== '') ? $fitid : null,
            );
        }

        return new NormalizationResult($drafts, $skipped);
    }

    /**
     * @return list<string>
     */
    private function transactionBlocks(string $content): array
    {
        preg_match_all('/<STMTTRN>(.*?)<\/STMTTRN>/is', $content, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Read a leaf tag's value. Works for both SGML (value runs to end of line, no
     * closing tag) and XML (value runs to the closing tag) because it stops at the
     * first "<" or line break.
     */
    private function tag(string $block, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'>\s*([^<\r\n]+)/i', $block, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1]);

        return $value === '' ? null : $value;
    }

    /**
     * OFX dates are YYYYMMDD, optionally followed by HHMMSS and a "[tz:LABEL]" suffix.
     */
    private function normalizeDate(string $raw): ?string
    {
        if (preg_match('/^\s*(\d{8})/', $raw, $matches) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Ymd', $matches[1]);
        } catch (\Throwable) {
            return null;
        }

        if ($date === false || $date->format('Ymd') !== $matches[1]) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
