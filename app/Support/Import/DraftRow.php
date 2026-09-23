<?php

namespace App\Support\Import;

/**
 * The normalized import-draft contract (task #1247). Every extractor — CSV now,
 * browser-local PDF/image later (#1248) — produces rows in this shape before they
 * reach staging. Values are already normalized: ISO date, integer minor units,
 * and a normalized payee alongside the raw source text.
 */
final class DraftRow
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $date,
        public string $payee,
        public string $rawPayee,
        public int $amountCents,
        public array $warnings = [],
    ) {}
}
