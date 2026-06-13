<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of a single-selection tally (FPTP) or a binary motion (YesNo).
 *
 * Carries our D1/D9/D10 domain fields on top of the bare tally:
 *  - validVotes  the percentage / pass denominator (real option votes only)
 *  - abstentions legitimate abstain tokens (abstainable elections)
 *  - invalid     out-of-options / blank / non-scalar answers (never winnable)
 *  - winners     always an array (never null); empty when there is no winner
 *  - passed / passThreshold  YesNo motion semantics (D4/D5); null for FPTP
 */
final readonly class SimpleVoteResult implements ComponentResult
{
    /**
     * @param array<string, int> $state Vote tallies per option (full roster, D10)
     * @param array<string> $winners Options sharing the highest tally
     */
    public function __construct(
        public array $state,
        public int $totalVotes,
        public ?string $winner,
        public array $winners,
        public int $validVotes = 0,
        public int $abstentions = 0,
        public int $invalid = 0,
        public ?bool $passed = null,
        public int|float|string|null $passThreshold = null,
    ) {}

    public static function empty(): self
    {
        return new self(
            state: [],
            totalVotes: 0,
            winner: null,
            winners: [],
        );
    }

    /**
     * Plurality tally (FPTP) over the already-reconciled state plus the audit
     * counts. valid_votes (D1) is the option-vote sum and the percentage base;
     * abstain/invalid are excluded from the winner by construction (D9).
     *
     * @param array<string, int> $state full roster, real options only
     */
    public static function fromState(array $state, int $abstentions = 0, int $invalid = 0): self
    {
        $validVotes = array_sum($state);
        $totalVotes = $validVotes + $abstentions + $invalid;

        if ($validVotes === 0 || $state === []) {
            return new self(
                state: $state,
                totalVotes: $totalVotes,
                winner: null,
                winners: [],
                validVotes: $validVotes,
                abstentions: $abstentions,
                invalid: $invalid,
            );
        }

        $winners = array_keys($state, max($state), true);
        $winner = count($winners) > 1 ? 'tie' : (string) $winners[0];

        return new self(
            state: $state,
            totalVotes: $totalVotes,
            winner: $winner,
            winners: array_map('strval', $winners),
            validVotes: $validVotes,
            abstentions: $abstentions,
            invalid: $invalid,
        );
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'valid_votes' => $this->validVotes,
            'abstentions' => $this->abstentions,
            'invalid' => $this->invalid,
            'total_votes' => $this->totalVotes,
            'winner' => $this->winner,
            'winners' => $this->winners,
            'passed' => $this->passed,
            'pass_threshold' => $this->passThreshold,
        ];
    }
}
