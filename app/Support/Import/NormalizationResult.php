<?php

namespace App\Support\Import;

/**
 * The outcome of normalizing raw extractor rows: the draft rows that are safe to
 * stage, plus the rows that could not be normalized. Skipped rows are reported,
 * never silently discarded (task #1247, user story 7).
 */
final class NormalizationResult
{
    /**
     * @param  list<DraftRow>  $drafts
     * @param  list<array{row: int, reason: string}>  $skipped
     */
    public function __construct(
        public array $drafts,
        public array $skipped,
    ) {}
}
