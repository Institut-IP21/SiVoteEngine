<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * A factual, owner-approved "how does this method compare" entry for a
 * ballot component type: a plain "number elected" descriptor (both
 * locales — e.g. "1" for a single-winner method, "Multiple (top K)" for a
 * seats-based one) plus three 1-5 integer ratings.
 *
 * The ratings are structural facts about the method, not language, so they
 * live as plain ints hardcoded in the component class (see
 * `AbstractBallotComponent::getComparisonRatings()`) rather than in a lang
 * file; only the descriptor text, the column labels, and the disclaimer
 * (shared across every type, see `BallotService::getStatuteText()`'s
 * `comparison_meta`) are translated strings.
 *
 * This is an educational comparison, not a claim of precision — see the
 * `comparison_meta.disclaimer` string served alongside every entry.
 */
final readonly class MethodComparison
{
    /**
     * @param array{en: string, sl: string} $elected
     * @param array{true_prefs: int, manipulation: int, simplicity: int} $ratings
     */
    public function __construct(
        public string $type,
        public array $elected,
        public array $ratings,
    ) {}

    /**
     * @return array{
     *     type: string,
     *     elected: array{en: string, sl: string},
     *     ratings: array{true_prefs: int, manipulation: int, simplicity: int},
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'elected' => $this->elected,
            'ratings' => $this->ratings,
        ];
    }
}
