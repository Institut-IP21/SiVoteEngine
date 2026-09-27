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
            'method' => __('components.approval.method'),
            'description' => __('components.approval.description'),
            'hint' => __('components.approval.hint'),
            'lay_explanation' => __('components.approval.lay_explanation'),
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
    protected function cardinality(): string
    {
        return 'multiple';
    }

    #[\Override]
    protected function getI18nStrings(): array
    {
        return [
            'name' => $this->bothLocales('components.approval.name'),
            'method' => $this->bothLocales('components.approval.method'),
        ];
    }

    #[\Override]
    protected function getStatuteTextParagraphs(): array
    {
        return [
            'en' => $this->statuteParagraphs('statute.approval', 'en'),
            'sl' => $this->statuteParagraphs('statute.approval', 'sl'),
        ];
    }

    #[\Override]
    protected function getAcademicTextParagraphs(): array
    {
        return [
            'en' => $this->academicText('academic.approval', 'en'),
            'sl' => $this->academicText('academic.approval', 'sl'),
        ];
    }

    /**
     * Approval voting (D1/D2/D9/D10). Per ballot: an absent key or null value is
     * an abstention when abstainable, else an invalid/blank ballot — neither is a
     * participant (`voters`) nor winnable. Otherwise the ballot participates and
     * each approved label is reconciled against options: known labels increment
     * `state`, anything else is `invalid` (never winnable). Rate is per-voter (D2).
     *
     * Top-K (`settings.seats`, default 1, clamped to [1, optionCount], mirroring
     * OrderedList's clamp): options with a count strictly above the cutoff (the
     * count of the option at rank K) are guaranteed a seat (`elected`); options
     * tied AT the cutoff are only `elected` when they exactly fill the remaining
     * seats — if seating all of them would overshoot K, they're `contested`
     * instead and the engine never arbitrarily picks among them (same
     * surface-don't-break idiom as OrderedListResult's bands/cutoffDecision).
     * `winner`/`winners` are unchanged — the seats-agnostic single-highest-count
     * option(s) — kept purely for back-compat with pre-top-K consumers.
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

        /** @var array<string, mixed> $settings */
        $settings = $component->settings ?? [];
        $optionCount = count($state);
        $rawSeatsValue = $settings['seats'] ?? 1;
        $rawSeats = is_scalar($rawSeatsValue) ? (int) $rawSeatsValue : 1;
        $seats = max(1, min($optionCount, $rawSeats));

        /** @var list<string> $warnings */
        $warnings = [];
        if ($seats !== $rawSeats) {
            // A non-numeric value (e.g. "abc") casts to 0, which would read as
            // a misleading "requested 0" — it was never a number, so say so
            // instead of reporting the accidental cast result.
            $warnings[] = is_numeric($rawSeatsValue)
                ? "seats clamped to {$seats} (requested {$rawSeats}, roster has {$optionCount})"
                : "seats clamped to {$seats} (invalid seats value, roster has {$optionCount})";
        }

        if ($totalApprovals === 0 || $state === []) {
            $winner = null;
            $winners = [];
            $elected = [];
            $contested = [];
            $contestedSeats = 0;
        } else {
            $winners = array_map('strval', array_keys($state, max($state), true));
            $winner = count($winners) > 1 ? 'tie' : $winners[0];

            // Stable sort (PHP 8+ guarantees stability) by count desc; ties keep
            // their original roster order — a deterministic DISPLAY order only,
            // never used to break the tie itself.
            $sortedState = $state;
            uasort($sortedState, fn (int $a, int $b): int => $b <=> $a);
            $rankedOptions = array_keys($sortedState);
            $cutoffCount = $sortedState[$rankedOptions[$seats - 1]];

            $aboveCutoff = [];
            $atCutoff = [];
            foreach ($rankedOptions as $option) {
                $count = $sortedState[$option];
                if ($count > $cutoffCount) {
                    $aboveCutoff[] = $option;
                } elseif ($count === $cutoffCount) {
                    $atCutoff[] = $option;
                }
            }

            // Contested only when seating every option tied at the cutoff would
            // exceed the remaining seats — otherwise they exactly fill them and
            // are elected outright (this is what makes seats=1 with a unique max
            // reproduce today's single-winner result byte-for-byte).
            $isContested = count($aboveCutoff) < $seats && count($aboveCutoff) + count($atCutoff) > $seats;
            $contested = $isContested ? $atCutoff : [];
            $contestedSeats = $isContested ? $seats - count($aboveCutoff) : 0;
            $elected = $isContested ? $aboveCutoff : [...$aboveCutoff, ...$atCutoff];
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
            seats: $seats,
            elected: $elected,
            contested: $contested,
            contestedSeats: $contestedSeats,
            warnings: $warnings,
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
