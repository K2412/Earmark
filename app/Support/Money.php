<?php

namespace App\Support;

final class Money
{
    public static function format(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $formatted = number_format($absolute / 100, 2);

        return ($negative ? '-' : '').'$'.$formatted;
    }

    /**
     * Parse a human-entered money string (e.g. "$1,234.50", "(12.30)", "-8") into
     * signed integer cents. Returns null when the string carries no parseable
     * numeric value. Parentheses denote a negative amount.
     */
    public static function parseToCents(string $raw): ?int
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        $negative = false;

        if (preg_match('/^\((.*)\)$/', $value, $matches) === 1) {
            $negative = true;
            $value = $matches[1];
        }

        if (str_contains($value, '-')) {
            $negative = true;
        }

        // Keep digits and separators only, then treat the last separator as the decimal point.
        $value = preg_replace('/[^0-9.,]/', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');
        $decimalPos = max($lastDot === false ? -1 : $lastDot, $lastComma === false ? -1 : $lastComma);

        if ($decimalPos === -1) {
            $integer = preg_replace('/\D/', '', $value) ?? '';
            $fraction = '';
        } else {
            $integer = preg_replace('/\D/', '', substr($value, 0, $decimalPos)) ?? '';
            $fraction = preg_replace('/\D/', '', substr($value, $decimalPos + 1)) ?? '';
        }

        if ($integer === '' && $fraction === '') {
            return null;
        }

        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) ($integer === '' ? '0' : $integer)) * 100 + (int) $fraction;

        return $negative ? -$cents : $cents;
    }
}
