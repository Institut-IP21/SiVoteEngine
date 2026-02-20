<?php

declare(strict_types=1);

namespace App\BallotComponents\RankedChoice\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\RankedChoiceResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Ranked Choice Voting (Instant Runoff) ballot component.
 *
 * Voters rank options in order of preference. If no option has a majority,
 * the option with the fewest votes is eliminated and those votes are
 * redistributed to the next preference until a winner is determined.
 */
final class RankedChoice extends AbstractBallotComponent
{
    #[\Override]
    protected function needsOptions(): bool
    {
        return true;
    }

    #[\Override]
    protected function usesLivewireForm(): bool
    {
        return true;
    }

    #[\Override]
    protected function getStrings(): array
    {
        return [
            'name' => __('components.rankedchoice.name'),
            'description' => __('components.rankedchoice.description'),
        ];
    }

    #[\Override]
    protected function getOptionsValidatorRules(): array
    {
        return [
            'options' => 'bail|required|array|min:2',
            'options.*' => 'bail|required|string|distinct|min:1',
        ];
    }

    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component): ComponentResult
    {
        if ($votes->isEmpty()) {
            return RankedChoiceResult::empty();
        }

        $rounds = $this->runIteration($votes, $component);

        return RankedChoiceResult::fromRounds($rounds);
    }

    /**
     * Run one round of ranked choice elimination.
     *
     * Recursively eliminates the lowest vote-getter until a winner has a majority
     * or only 2 options remain.
     *
     * @param Collection<int, mixed> $votes All votes cast for this component
     * @param array<int, array<string, mixed>> $rounds Previous elimination rounds
     * @param array<string> $omit Options eliminated in previous rounds
     * @return array<int, array<string, mixed>> All rounds including this one
     */
    private function runIteration(
        Collection $votes,
        BallotComponent $component,
        array $rounds = [],
        array $omit = [],
    ): array {
        // Only consider non-eliminated options
        $options = array_diff($component->options, $omit);

        // Initialize all options to 0 votes
        $state = array_fill_keys($options, 0);

        // Count votes that were actually cast
        $numberOfVotesCast = $votes
            ->filter(fn ($vote): bool => !empty($vote->values))
            ->count();

        // Tally first-choice votes (ignoring eliminated options)
        foreach ($votes as $vote) {
            if (empty($vote->values) || !array_key_exists($component->id, $vote->values)) {
                continue;
            }

            $values = $vote->values[$component->id];

            if (!is_array($values) || count($values) === 0) {
                continue;
            }

            // Remove eliminated options from the beginning of the ranking
            while (count($values) > 0 && in_array($values[0], $omit, true)) {
                array_shift($values);
            }

            // The first remaining value is the effective vote
            $first = array_shift($values);

            if ($first === null || !isset($state[$first])) {
                continue;
            }

            $state[$first]++;
        }

        // Check if current leader has a majority
        $maxVotes = max($state) ?: 0;
        $currentWinnerHasMajority = $numberOfVotesCast > 0
            && $maxVotes >= ($numberOfVotesCast / 2 + 1);

        // Continue elimination if more than 2 options remain and no majority
        if (count($state) > 2 && !$currentWinnerHasMajority) {
            $minVotes = min($state);
            $omitees = array_keys($state, $minVotes);

            // Special case: if multiple options tied for last with 0 votes, eliminate all
            if (count($omitees) > 1) {
                if ($minVotes === 0) {
                    $state = $this->annotateStateForOmission($state, $omit, $omitees);
                    $nextOmit = [...$omit, ...$omitees];
                    $rounds = [...$rounds, $state];
                    return $this->runIteration($votes, $component, $rounds, $nextOmit);
                }

                // Tied for last with votes: split into multiple scenarios
                $splits = [
                    '_state' => $state,
                    'splitElimination' => [],
                ];
                foreach ($omitees as $omitee) {
                    $splitOmit = [...$omit, $omitee];
                    $splits['splitElimination'][$omitee] = $this->runIteration($votes, $component, [], $splitOmit);
                }
                return [...$rounds, $splits];
            }

            // Single option to eliminate
            $omitee = $omitees[0];
            $state = $this->annotateStateForOmission($state, $omit, $omitee);
            $nextOmit = [...$omit, $omitee];
            $rounds = [...$rounds, $state];

            return $this->runIteration($votes, $component, $rounds, $nextOmit);
        }

        // Final round - annotate with winner
        $state = $this->annotateStateForVictory($state);
        $rounds = [...$rounds, $state];

        return $rounds;
    }

    /**
     * Mark the winner in a round state.
     *
     * @param array<string, int> $state
     * @return array<string, mixed>
     */
    private function annotateStateForVictory(array $state): array
    {
        $maxVotes = max($state) ?: 0;
        $winners = array_keys($state, $maxVotes);

        $state['winner'] = count($winners) > 1 ? 'tie' : $winners[0];

        return $state;
    }

    /**
     * Mark eliminated option(s) in a round state.
     *
     * @param array<string, int> $state
     * @param array<string> $previouslyOmitted
     * @param string|array<string> $omitee
     * @return array<string, mixed>
     */
    private function annotateStateForOmission(array $state, array $previouslyOmitted, string|array $omitee): array
    {
        $state['eliminated'] = is_array($omitee) ? implode(', ', $omitee) : $omitee;
        $state['eliminated_previously'] = $previouslyOmitted;

        return $state;
    }

    /**
     * Get frequency matrix of options at each ranking position.
     *
     * @param Collection<int, mixed> $votes
     * @return array<string, array<int, int>>
     */
    public function getTotals(Collection $votes, BallotComponent $component): array
    {
        $totals = [];

        foreach ($votes as $vote) {
            if (empty($vote->values)) {
                continue;
            }

            $values = $vote->values[$component->id] ?? [];

            foreach ($component->options as $option) {
                if (!isset($totals[$option])) {
                    $totals[$option] = array_fill(0, count($component->options), 0);
                }

                $pos = array_search($option, $values, true);
                if ($pos !== false) {
                    $totals[$option][$pos]++;
                }
            }
        }

        return $totals;
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        return new ValidationRules([
            $component->id => [$election->abstainable ? 'nullable' : 'required'],
            "{$component->id}.*" => [Rule::in($component->options)],
        ]);
    }

    #[\Override]
    public function valuesToCsv(array $values, string $componentId): string
    {
        if (!array_key_exists($componentId, $values)) {
            return '';
        }

        $value = $values[$componentId];
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

}
