<?php

namespace App\Services\Import;

use App\Support\Import\DraftRow;
use App\Support\Import\NormalizationResult;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns raw CSV cells plus a column mapping into normalized draft rows. This is
 * the CSV extractor for the shared import contract: dates become ISO strings and
 * amounts become signed integer cents, authoritatively, on the server.
 */
class CsvImportNormalizer
{
    public const PARSER_VERSION = 'earmark.csv.v1';

    public const NO_DESCRIPTION = '(no description)';

    /**
     * Supported date formats offered to the mapper (label => PHP format).
     *
     * @var array<string, string>
     */
    public const DATE_FORMATS = [
        'YYYY-MM-DD' => 'Y-m-d',
        'MM/DD/YYYY' => 'm/d/Y',
        'DD/MM/YYYY' => 'd/m/Y',
        'YYYY/MM/DD' => 'Y/m/d',
        'DD-MM-YYYY' => 'd-m-Y',
        'DD Mon YYYY' => 'd M Y',
    ];

    /**
     * @param  list<array{date?: ?string, payee?: ?string, amount?: ?string, debit?: ?string, credit?: ?string}>  $rows
     * @param  array{date_format: string, amount_mode: string, sign?: ?string}  $mapping
     */
    public function normalize(array $rows, array $mapping): NormalizationResult
    {
        $format = self::DATE_FORMATS[$mapping['date_format']] ?? $mapping['date_format'];
        $mode = $mapping['amount_mode'];
        $sign = $mapping['sign'] ?? 'negative_is_outflow';

        $drafts = [];
        $skipped = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 1;

            $date = $this->normalizeDate((string) ($row['date'] ?? ''), $format);

            if ($date === null) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Unrecognized date.'];

                continue;
            }

            $amount = $this->normalizeAmount($row, $mode, $sign);

            if ($amount === null) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'No parseable amount.'];

                continue;
            }

            $rawPayee = trim((string) ($row['payee'] ?? ''));
            $payee = $rawPayee === '' ? self::NO_DESCRIPTION : preg_replace('/\s+/', ' ', $rawPayee);

            $warnings = $payee === self::NO_DESCRIPTION ? ['Missing description.'] : [];

            $drafts[] = new DraftRow($date, (string) $payee, $rawPayee, $amount, $warnings);
        }

        return new NormalizationResult($drafts, $skipped);
    }

    private function normalizeDate(string $raw, string $format): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $raw);
        } catch (Throwable) {
            return null;
        }

        if ($date === false) {
            return null;
        }

        // Reject loose parses (e.g. an overflowed month) by requiring a round-trip.
        if ($date->format($format) !== $raw) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    /**
     * @param  array{amount?: ?string, debit?: ?string, credit?: ?string}  $row
     */
    private function normalizeAmount(array $row, string $mode, string $sign): ?int
    {
        if ($mode === 'debit_credit') {
            $debit = Money::parseToCents((string) ($row['debit'] ?? ''));
            $credit = Money::parseToCents((string) ($row['credit'] ?? ''));

            if ($debit !== null && $debit !== 0) {
                return -abs($debit);
            }

            if ($credit !== null && $credit !== 0) {
                return abs($credit);
            }

            return null;
        }

        $amount = Money::parseToCents((string) ($row['amount'] ?? ''));

        if ($amount === null) {
            return null;
        }

        return $sign === 'positive_is_outflow' ? -$amount : $amount;
    }
}
