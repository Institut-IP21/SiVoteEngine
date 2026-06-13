<?php

declare(strict_types=1);

namespace App\BallotComponents\ApprovalVote\v1;

use App\BallotComponents\DTOs\ApprovalVoteResult;
use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Approval Vote ballot component.
 *
 * Multiple selection voting where voters can approve multiple options.
 */
final class ApprovalVote extends AbstractBallotComponent
{
    #[\Override]
    protected function needsOptions(): bool
    {
        return true;
    }

    #[\Override]
    protected function getStrings(): array
    {
        return [
            'name' => __('components.approval.name'),
            'description' => __('components.approval.description'),
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

    /**
     * Approval voting (D1/D2/D9/D10). Per ballot: an absent key or null value is
     * an abstention when abstainable, else an invalid/blank ballot — neither is a
     * participant (`voters`) nor winnable. Otherwise the ballot participates and
     * each approved label is reconciled against options: known labels increment
     * `state`, anything else is `invalid` (never winnable). Rate is per-voter (D2).
     */
    #[\Override]
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult
    {
        /** @var array<int, string> $options */
        $options = $component->options ?? [];

        // D10: full roster — seed every option at 0, in options order.
        $state = [];
        foreach ($options as $option) {
            $state[(string) $option] = 0;
        }
        $allowed = array_flip(array_map('strval', $options));

        $voters = 0;
        $abstentions = 0;
        $invalid = 0;

        foreach ($votes as $vote) {
            $values = is_array($vote->values) ? $vote->values : [];
            $hasKey = array_key_exists($component->id, $values);
            $answer = $hasKey ? $values[$component->id] : null;

            // Absent key or null: not a participant. Abstention only when
            // abstainable (D9); otherwise invalid/blank. Neither counts in voters.
            if (!$hasKey || $answer === null) {
                $abstainable ? $abstentions++ : $invalid++;
                continue;
            }

            $voters++;
            $approvals = is_array($answer) ? $answer : [$answer];

            foreach ($approvals as $label) {
                if (is_scalar($label) && isset($allowed[(string) $label])) {
                    $state[(string) $label]++;
                } else {
                    $invalid++;
                }
            }
        }

        $totalApprovals = array_sum($state);

        if ($totalApprovals === 0 || $state === []) {
            $winner = null;
            $winners = [];
        } else {
            $winners = array_map('strval', array_keys($state, max($state), true));
            $winner = count($winners) > 1 ? 'tie' : $winners[0];
        }

        return new ApprovalVoteResult(
            state: $state,
            voters: $voters,
            totalApprovals: $totalApprovals,
            abstentions: $abstentions,
            invalid: $invalid,
            totalBallots: $voters + $abstentions,
            winner: $winner,
            winners: $winners,
        );
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        // The submission must be an array of options. Without the explicit
        // `array` rule a crafted scalar value bypasses the `.*` option
        // whitelist entirely and is stored verbatim. `distinct` prevents a
        // single ballot from approving the same option more than once.
        return new ValidationRules([
            $component->id => [$election->abstainable ? 'nullable' : 'required', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in($component->options)],
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
