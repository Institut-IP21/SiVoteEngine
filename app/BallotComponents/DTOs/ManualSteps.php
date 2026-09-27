<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Bilingual by-hand calculation steps for a ballot component type: a short,
 * ordered, lay-readable procedure describing what a scrutineer with paper
 * would actually do to work out the result — grounded in the REAL
 * calculator's logic (including its specific tie-break/quorum/threshold
 * rules), not a textbook description of the method. Parallel to
 * `StatuteText` (the legal-reference clause text) and `AcademicText` (the
 * neutral explanation + pros/cons); `en`/`sl` are fetched explicitly in BOTH
 * locales regardless of the request locale (same rule as those two DTOs) so
 * a locale toggle needs no second round trip.
 */
final readonly class ManualSteps
{
    /**
     * @param list<string> $en
     * @param list<string> $sl
     */
    public function __construct(
        public string $type,
        public array $en,
        public array $sl,
    ) {}

    /**
     * @return array{type: string, en: list<string>, sl: list<string>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'en' => $this->en,
            'sl' => $this->sl,
        ];
    }
}
