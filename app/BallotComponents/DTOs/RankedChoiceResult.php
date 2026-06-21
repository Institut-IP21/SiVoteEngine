<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of an instant-runoff (ranked-choice) tally over a LINEAR rounds list
 * (D6). The terminal round carries either a single `winner` label (conclusive)
 * or `winner === null` plus a `tied` list (non-conclusive). The literal 'tie'
 * sentinel never appears, so it can never leak into `winners` (#14 guard).
 */
final readonly class RankedChoiceResult implements ComponentResult
{
    /**
     * @param array<int, array<string, mixed>> $rounds Elimination rounds (flat, oldest first)
     * @param array<string> $winners Conclusive winner (1) or the tied labels (non-conclusive)
     * @param array{cast?: int, blank?: int, invalid_only?: int, counted?: int} $accounting Ballot reconciliation (audit)
     * @param array<string, array<int, int>> $preferences First-preference position matrix (option × rank), audit cross-check
     */
    public function __construct(
        public array $rounds,
        public array $winners,
        public bool $conclusive,
        public ?string $conclusiveWinner,
        public array $accounting = [],
        public array $preferences = [],
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
     * Derive the result from the LINEAR rounds list using our deterministic
     * final-state logic (D6): the terminal round's `winner` is a single label
     * (conclusive) or null with an optional `tied` list (non-conclusive).
     *
     * @param array<int, array<string, mixed>> $rounds
     * @param array{cast?: int, blank?: int, invalid_only?: int, counted?: int} $accounting
     * @param array<string, array<int, int>> $preferences
     */
    public static function fromRounds(array $rounds, array $accounting = [], array $preferences = []): self
    {
        if ($rounds === []) {
            return self::empty();
        }

        $final = end($rounds);
        // Defensive guard kept intentionally: $rounds is typed array-of-arrays so
        // PHPStan sees this as always-true, but the runtime check is retained.
        // @phpstan-ignore-next-line function.alreadyNarrowedType
        if (!is_array($final)) {
            return new self(rounds: $rounds, winners: [], conclusive: false, conclusiveWinner: null, accounting: $accounting, preferences: $preferences);
        }

        $winner = $final['winner'] ?? null;

        if (is_string($winner)) {
            return new self(
                rounds: $rounds,
                winners: [$winner],
                conclusive: true,
                conclusiveWinner: $winner,
                accounting: $accounting,
                preferences: $preferences,
            );
        }

        // Non-conclusive: surface the tied option labels (if any were recorded).
        $tied = [];
        if (isset($final['tied']) && is_array($final['tied'])) {
            $tied = array_values(array_map('strval', $final['tied']));
        }

        return new self(
            rounds: $rounds,
            winners: $tied,
            conclusive: false,
            conclusiveWinner: null,
            accounting: $accounting,
            preferences: $preferences,
        );
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
            'accounting' => $this->accounting,
            'preferences' => $this->preferences,
        ];
    }
}
