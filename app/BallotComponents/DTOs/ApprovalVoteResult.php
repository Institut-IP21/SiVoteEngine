<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of an approval vote (D2/D9/D10).
 *
 * The approval rate is computed per participating voter (approvals ÷ voters),
 * so option rows may collectively exceed 100%. `voters` counts participating
 * ballots only (abstentions/invalid excluded); `totalApprovals` is the sum of
 * all real-option approvals; `totalBallots` is voters + abstentions.
 */
final readonly class ApprovalVoteResult implements ComponentResult
{
    /**
     * @param array<string, int> $state Approvals per option (full roster, D10)
     * @param array<string> $winners Options sharing the most approvals
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
        ];
    }
}
