<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use App\BallotComponents\DTOs\ComponentResult;
use App\BallotComponents\DTOs\OrderedListResult;
use App\BallotComponents\DTOs\ValidationRules;
use App\BallotComponents\Support\AbstractBallotComponent;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Ordered-list ballot component: elects a K-from-N ordered list via Ranked
 * Pairs (margins). Orchestrates the pure calculation classes (PairwiseMatrix
 * -> RankedPairsLock -> PositionResolver, plus the optional QuotaCorrector
 * and RunnerResolutionApplier) and returns an OrderedListResult. Ballot
 * input reuses the RankedChoice ranker: an approval-then-rank submission is
 * simply a ranking of a subset of the roster.
 */
final class OrderedList extends AbstractBallotComponent
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
            'name' => __('components.orderedlist.name'),
            'description' => __('components.orderedlist.description'),
            'hint' => __('components.orderedlist.hint'),
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
    public function calculateResults(Collection $votes, BallotComponent $component, bool $abstainable = false): ComponentResult
    {
        $roster = array_values(array_map('strval', $component->options ?? []));
        $n = count($roster);

        /** @var array<string, mixed> $settings */
        $settings = $component->settings ?? [];

        /** @var list<string> $warnings */
        $warnings = [];

        $rawSeatsValue = $settings['seats'] ?? $n;
        $rawSeats = is_scalar($rawSeatsValue) ? (int) $rawSeatsValue : $n;
        $seats = max(1, min($n, $rawSeats));
        if ($seats !== $rawSeats && $votes->isNotEmpty()) {
            $warnings[] = "seats clamped to {$seats} (requested {$rawSeats}, roster has {$n})";
        }

        $categories = $this->parseCategories($settings);

        $quotaParsed = $this->parseQuota($settings);
        $quota = $quotaParsed['quota'];
        if ($quotaParsed['warning'] !== null) {
            $warnings[] = $quotaParsed['warning'];
        }

        $parsed = $this->accountAndParse($votes, $component, $roster);
        $counted = $parsed['counted'];
        $accounting = $parsed['accounting'];

        if ($counted === []) {
            return new OrderedListResult(
                seats: $seats,
                ranking: [],
                elected: [],
                bands: [],
                cutoffDecision: null,
                resolutions: [],
                final: null,
                corrected: null,
                official: 'natural',
                lockInLog: [],
                pairwise: ['candidates' => $roster, 'matrix' => []],
                accounting: $accounting,
                warnings: $warnings,
            );
        }

        $pairwiseMatrix = new PairwiseMatrix($counted, $roster);
        $lock = new RankedPairsLock($pairwiseMatrix->decisivePairs(), $roster);
        $positions = new PositionResolver($roster, $lock->reachable(), $pairwiseMatrix->matrix(), $seats);

        $corrected = null;
        $quotaBinding = false;
        if ($quota !== null) {
            $quotaCorrector = new QuotaCorrector(
                $positions->ranking(),
                $positions->cutoffDecision(),
                $positions->bands(),
                $categories,
                $quota,
                $seats,
            );
            $corrected = $quotaCorrector->result();
            $quotaBinding = $quota['binding'];
            $warnings = [...$warnings, ...$quotaCorrector->warnings()];
        }

        $rawResolutions = $component->getAttribute('runner_resolutions');
        /** @var list<array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}> $resolutions */
        $resolutions = is_array($rawResolutions) ? $rawResolutions : [];

        $applier = new RunnerResolutionApplier(
            $positions->ranking(),
            $positions->bands(),
            $positions->cutoffDecision(),
            $resolutions,
            $lock->reachable(),
            $seats,
        );
        $final = $applier->applied() ? $applier->result() : null;
        $warnings = [...$warnings, ...$applier->warnings()];

        $official = ($corrected !== null && $quotaBinding && !$corrected['infeasible'] && !$corrected['provisional'])
            ? 'corrected'
            : 'natural';

        return new OrderedListResult(
            seats: $seats,
            ranking: $positions->ranking(),
            elected: $positions->elected(),
            bands: $positions->bands(),
            cutoffDecision: $positions->cutoffDecision(),
            resolutions: $resolutions,
            final: $final,
            corrected: $corrected,
            official: $official,
            lockInLog: $lock->log(),
            pairwise: ['candidates' => $pairwiseMatrix->candidates(), 'matrix' => $pairwiseMatrix->matrix()],
            accounting: $accounting,
            warnings: $warnings,
        );
    }

    /**
     * Filter settings.categories down to a clean candidate-label => category
     * string map; anything not a string value is dropped rather than guessed at.
     *
     * @param array<string, mixed> $settings
     * @return array<string, string>
     */
    private function parseCategories(array $settings): array
    {
        $raw = $settings['categories'] ?? null;
        if (!is_array($raw)) {
            return [];
        }

        $categories = [];
        foreach ($raw as $key => $value) {
            if (is_string($value)) {
                $categories[(string) $key] = $value;
            }
        }

        return $categories;
    }

    /**
     * Parse settings.quota defensively: a well-formed quota needs a string
     * category, a min/max type, and an integer count >= 1; binding defaults
     * to true when absent or not a bool. Anything else is dropped with a
     * warning rather than guessed at.
     *
     * @param array<string, mixed> $settings
     * @return array{quota: array{category:string,type:string,count:int,binding:bool}|null, warning: string|null}
     */
    private function parseQuota(array $settings): array
    {
        if (!array_key_exists('quota', $settings)) {
            return ['quota' => null, 'warning' => null];
        }

        $raw = $settings['quota'];
        if (!is_array($raw)) {
            return ['quota' => null, 'warning' => 'quota settings malformed — ignored'];
        }

        $category = $raw['category'] ?? null;
        $type = $raw['type'] ?? null;
        $count = $raw['count'] ?? null;
        $binding = $raw['binding'] ?? null;

        if (!is_string($category) || !is_string($type) || !in_array($type, ['min', 'max'], true) || !is_int($count) || $count < 1) {
            return ['quota' => null, 'warning' => 'quota settings malformed — ignored'];
        }

        return [
            'quota' => [
                'category' => $category,
                'type' => $type,
                'count' => $count,
                'binding' => is_bool($binding) ? $binding : true,
            ],
            'warning' => null,
        ];
    }

    /**
     * Ballot accounting for the audit view, mirroring RankedChoice's
     * accountBallots: how many ballots were cast, left this question BLANK
     * (a non-array/empty value), or were INVALID for it (ranked only
     * options outside the roster or only duplicates of an already-seen
     * label). The tally silently drops non-list/out-of-roster/duplicate
     * entries exactly as RankedChoice does, preserving order.
     *
     * @param Collection<int, \App\Models\Vote> $votes
     * @param list<string> $roster
     * @return array{counted: list<list<string>>, accounting: array{cast:int,blank:int,invalid_only:int,counted:int}}
     */
    private function accountAndParse(Collection $votes, BallotComponent $component, array $roster): array
    {
        $cast = $votes->count();
        $blank = 0;
        $invalidOnly = 0;
        /** @var list<list<string>> $counted */
        $counted = [];

        foreach ($votes as $vote) {
            $values = $vote->values ?? null;
            $ranking = (is_array($values) && array_key_exists($component->id, $values))
                ? $values[$component->id]
                : null;

            if (!is_array($ranking) || count($ranking) === 0) {
                $blank++;
                continue;
            }

            /** @var list<string> $clean */
            $clean = [];
            foreach ($ranking as $pref) {
                if (!is_scalar($pref)) {
                    continue;
                }
                $label = (string) $pref;
                if (!in_array($label, $roster, true) || in_array($label, $clean, true)) {
                    continue;
                }
                $clean[] = $label;
            }

            if ($clean === []) {
                $invalidOnly++;
                continue;
            }

            $counted[] = $clean;
        }

        return [
            'counted' => $counted,
            'accounting' => [
                'cast' => $cast,
                'blank' => $blank,
                'invalid_only' => $invalidOnly,
                'counted' => $cast - $blank - $invalidOnly,
            ],
        ];
    }

    #[\Override]
    public function getSubmissionValidator(BallotComponent $component, Election $election): ValidationRules
    {
        // The submission must be an array (a ranking). Without the explicit
        // `array` rule a crafted scalar value bypasses the `.*` option
        // whitelist entirely and is stored verbatim. `distinct` rejects a
        // ranking that lists the same option more than once.
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
