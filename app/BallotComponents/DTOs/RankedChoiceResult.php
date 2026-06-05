<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

final readonly class RankedChoiceResult implements ComponentResult
{
    /**
     * @param array<int, array<string, mixed>> $rounds Elimination rounds
     * @param array<string> $winners Unique winners across all branches
     */
    public function __construct(
        public array $rounds,
        public array $winners,
        public bool $conclusive,
        public ?string $conclusiveWinner,
    ) {}

    public static function empty(): self
    {
        return new self(
            rounds: [],
            winners: [],
            conclusive: false,
            conclusiveWinner: null,
        );
    }

    /**
     * Create from rounds data, extracting winner information.
     *
     * @param array<int, array<string, mixed>> $rounds
     */
    public static function fromRounds(array $rounds): self
    {
        if ($rounds === []) {
            return self::empty();
        }

        $winners = self::extractWinners($rounds);
        $uniqueWinners = array_unique($winners);
        $conclusive = count($uniqueWinners) === 1;

        return new self(
            rounds: $rounds,
            winners: $uniqueWinners,
            conclusive: $conclusive,
            conclusiveWinner: $conclusive ? array_values($uniqueWinners)[0] : null,
        );
    }

    /**
     * @param array<mixed> $rounds
     * @return array<string>
     */
    private static function extractWinners(array $rounds): array
    {
        $winners = [];
        array_walk_recursive($rounds, function (mixed $value, mixed $key) use (&$winners): void {
            if ($key === 'winner' && is_string($value)) {
                $winners[] = $value;
            }
        });
        return $winners;
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'rounds' => $this->rounds,
            'result' => [
                'winners' => $this->winners,
                'conclussive' => $this->conclusive, // Note: preserving original typo for view compatibility
                'conclussive_winner' => $this->conclusiveWinner,
            ],
        ];
    }
}
