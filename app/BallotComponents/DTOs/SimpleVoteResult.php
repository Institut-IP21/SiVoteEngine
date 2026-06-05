<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

final readonly class SimpleVoteResult implements ComponentResult
{
    /**
     * @param array<string, int> $state Vote tallies per option
     * @param array<string>|null $winners Options with highest vote count
     */
    public function __construct(
        public array $state,
        public int $totalVotes,
        public ?string $winner,
        public ?array $winners,
    ) {}

    public static function empty(): self
    {
        return new self(
            state: [],
            totalVotes: 0,
            winner: null,
            winners: null,
        );
    }

    /**
     * Create from vote tallies, automatically determining winner(s).
     *
     * @param array<string, int> $tallies
     */
    public static function fromTallies(array $tallies): self
    {
        if ($tallies === []) {
            return self::empty();
        }

        $maxVotes = max($tallies);
        $winners = array_keys($tallies, $maxVotes);
        $isTie = count($winners) > 1;

        return new self(
            state: $tallies,
            totalVotes: array_sum($tallies),
            winner: $isTie ? 'tie' : $winners[0],
            winners: $winners,
        );
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'total_votes' => $this->totalVotes,
            'winner' => $this->winner,
            'winners' => $this->winners,
        ];
    }
}
