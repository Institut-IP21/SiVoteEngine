<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of an approval vote (D2/D9/D10), now electing the top-K most-
 * approved options ("seats", default 1 — hard back-compat with the original
 * single-winner shape).
 *
 * The approval rate is computed per participating voter (approvals ÷ voters),
 * so option rows may collectively exceed 100%. `voters` counts participating
 * ballots only (abstentions/invalid excluded); `totalApprovals` is the sum of
 * all real-option approvals; `totalBallots` is voters + abstentions.
 *
 * `winner`/`winners` are kept unchanged — the option(s) sharing the single
 * highest approval count, regardless of `seats` — purely for back-compat
 * with pre-top-K consumers. The seat-aware outcome lives in `elected` /
 * `contested`: every option with a count strictly above the cutoff is
 * guaranteed a seat (`elected`); options genuinely tied for the last seat(s)
 * are never engine-picked — they're reported in `contested` together with
 * `contestedSeats`, the number of remaining seats they compete for (same
 * "surface, don't break" idiom as `OrderedListResult::$bands`/`$cutoffDecision`).
 */
final readonly class ApprovalVoteResult implements ComponentResult
{
    /**
     * @param array<string, int> $state Approvals per option (full roster, D10)
     * @param array<string> $winners Options sharing the most approvals (back-compat, seats-agnostic)
     * @param array<string> $elected Seated options (count-desc display order)
     * @param array<string> $contested Options genuinely tied for the last seat(s) (empty when none)
     * @param list<string> $warnings
     */
    public function __construct(
        public array $state,
        public int $voters,
        public int $totalApprovals,
        public int $abstentions,
        public int $invalid,
        public int $totalBallots,
        public ?string $winner,
        public array $winners,
        public int $seats,
        public array $elected,
        public array $contested,
        public int $contestedSeats,
        public array $warnings,
    ) {}

    public static function empty(): self
    {
        return new self(
            state: [],
            voters: 0,
            totalApprovals: 0,
            abstentions: 0,
            invalid: 0,
            totalBallots: 0,
            winner: null,
            winners: [],
            seats: 1,
            elected: [],
            contested: [],
            contestedSeats: 0,
            warnings: [],
        );
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'voters' => $this->voters,
            'total_approvals' => $this->totalApprovals,
            'abstentions' => $this->abstentions,
            'invalid' => $this->invalid,
            'total_ballots' => $this->totalBallots,
            'winner' => $this->winner,
            'winners' => $this->winners,
            'seats' => $this->seats,
            'elected' => $this->elected,
            'contested' => $this->contested,
            'contested_seats' => $this->contestedSeats,
            'warnings' => $this->warnings,
        ];
    }
}
