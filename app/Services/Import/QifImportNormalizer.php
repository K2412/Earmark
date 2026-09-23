<?php

namespace App\Services\Import;

use App\Support\Import\DraftRow;
use App\Support\Import\NormalizationResult;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Normalizes QIF (Quicken Interchange Format) exports into the shared draft-row
 * contract (#1269). QIF is line-tagged — D date, T/U amount, P payee, M memo — with
 * records separated by a lone "^". It carries no bank transaction id, so rows fall
 * back to the content fingerprint for duplicate detection. QIF has no standard date
 * format, so a prioritized set is tried with round-trip validation; North-American
 * MM/DD is preferred over DD/MM when a date is ambiguous.
 */
class QifImportNormalizer
{
    public const PARSER_VERSION = 'earmark.qif.v1';

    /**
     * @var list<string>
     */
    private const DATE_FORMATS = ['Y-m-d', "m/d'Y", "m/d'y", 'm/d/Y', 'm/d/y', 'd/m/Y', 'd/m/y'];

    public function normalize(string $content): NormalizationResult
    {
        $drafts = [];
        $skipped = [];
        $index = 0;

        foreach ($this->records($content) as $record) {
            $index++;

            $rawDate = $record['D'] ?? null;
            $rawAmount = $record['T'] ?? $record['U'] ?? null;
            $date = $rawDate !== null ? $this->normalizeDate($rawDate) : null;
            $amount = $rawAmount !== null ? Money::parseToCents($rawAmount) : null;

            if ($date === null) {
                $skipped[] = ['row' => $index, 'reason' => 'Missing or unreadable date.'];

                continue;
            }

            if ($amount === null) {
                $skipped[] = ['row' => $index, 'reason' => 'Missing or unreadable amount.'];

                continue;
            }

            $rawPayee = $record['P'] ?? $record['M'] ?? '';
            $payee = trim((string) preg_replace('/\s+/', ' ', $rawPayee));

            $drafts[] = new DraftRow(
                date: $date,
                payee: $payee,
                rawPayee: $rawPayee,
                amountCents: $amount,
                warnings: $payee === '' ? ['Missing description.'] : [],
            );
        }

        return new NormalizationResult($drafts, $skipped);
    }

    /**
     * Split the file into transaction records keyed by their single-letter field code.
     * "!" directive lines (e.g. "!Type:Bank") are ignored; the last value wins when a
     * code repeats within a record.
     *
     * @return list<array<string, string>>
     */
    private function records(string $content): array
    {
        $records = [];
        $current = [];
        $started = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            if ($line === '' || $line[0] === '!') {
                continue;
            }

            if ($line[0] === '^') {
                if ($started) {
                    $records[] = $current;
                }

                $current = [];
                $started = false;

                continue;
            }

            $current[$line[0]] = trim(substr($line, 1));
            $started = true;
        }

        if ($started) {
            $records[] = $current;
        }

        return $records;
    }

    private function normalizeDate(string $raw): ?string
    {
        $value = str_replace(' ', '', trim($raw));

        if ($value === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = CarbonImmutable::createFromFormat($format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
