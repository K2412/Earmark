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
}
