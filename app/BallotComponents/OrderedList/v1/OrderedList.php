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
 * Ordered-list ballot component: elects a K-from-N ordered list via the
 * Schulze method (beatpath, margins variant). Orchestrates the pure
 * calculation classes (PairwiseMatrix -> SchulzeBeatpath -> PositionResolver,
 * plus the optional QuotaCorrector) and returns an OrderedListResult. Ballot
 * input reuses the RankedChoice ranker: an approval-then-rank submission is
 * simply a ranking of a subset of the roster.
 *
 * Any residual genuine tie -- at the seat cutoff, in the order among already-
 * elected candidates, or inside a quota correction -- is SURFACED (reported,
 * never engine-picked): the organization's own rules (statute) name the
 * fallback, not this component.
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
        $laySegments = $this->laySegments('orderedlist');

        return [
            'name' => __('components.orderedlist.name'),
            'method' => __('components.orderedlist.method'),
            'description' => __('components.orderedlist.description'),
            'hint' => __('components.orderedlist.hint'),
            'lay_explanation' => $this->joinLaySegments($laySegments),
            'lay_segments' => $laySegments,
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
            'name' => $this->bothLocales('components.orderedlist.name'),
            'method' => $this->bothLocales('components.orderedlist.method'),
        ];
    }

    #[\Override]
    protected function getStatuteTextParagraphs(): array
    {
        return [
            'en' => $this->statuteParagraphs('statute.orderedlist', 'en'),
            'sl' => $this->statuteParagraphs('statute.orderedlist', 'sl'),
        ];
    }

    #[\Override]
    protected function getAcademicTextParagraphs(): array
    {
        return [
            'en' => $this->academicText('academic.orderedlist', 'en'),
            'sl' => $this->academicText('academic.orderedlist', 'sl'),
        ];
    }

    #[\Override]
    protected function getManualStepsParagraphs(): array
    {
        return [
            'en' => $this->statuteParagraphs('manual.orderedlist', 'en'),
            'sl' => $this->statuteParagraphs('manual.orderedlist', 'sl'),
        ];
    }

    #[\Override]
    protected function getComparisonRatings(): array
    {
        return ['true_prefs' => 5, 'manipulation' => 4, 'simplicity' => 2];
    }

    #[\Override]
    protected function getComparisonElected(): array
    {
        return $this->bothLocales('comparison.orderedlist.elected');
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
        /** @var list<string> $rawRoster */
        $rawRoster = array_values(array_map('strval', $component->options ?? []));
        // Defensive: the builder enforces `distinct` on options, but this
        // tabulator has no other path to guarantee it (e.g. options set
        // outside the builder). Without this, a duplicate label makes
        // PositionResolver emit the SAME candidate as two separate ranking
        // rows -- both independently eligible for 'elected' status -- which
        // can silently squeeze a real, distinct candidate out of the seat
        // count. Dedupe (first occurrence wins) and warn rather than guess.
        $roster = array_values(array_unique($rawRoster));
        $n = count($roster);

        /** @var array<string, mixed> $settings */
        $settings = $component->settings ?? [];

        /** @var list<string> $warnings */
        $warnings = [];
        if (count($roster) !== count($rawRoster)) {
            $warnings[] = 'roster had duplicate candidate labels — duplicates dropped, first occurrence kept';
        }

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
                corrected: null,
                official: 'natural',
                beatpath: ['strength' => [], 'winners' => []],
                pairwise: ['candidates' => $roster, 'matrix' => []],
                accounting: $accounting,
                warnings: $warnings,
            );
        }

        $pairwiseMatrix = new PairwiseMatrix($counted, $roster);
        $schulze = new SchulzeBeatpath($pairwiseMatrix->decisivePairs(), $roster);
        $positions = new PositionResolver($roster, $schulze->reachable(), $pairwiseMatrix->matrix(), $seats);

        $corrected = null;
        $quotaBinding = false;
        /** @var list<string> $quotaWarnings */
        $quotaWarnings = [];
        if ($quota !== null) {
            $quotaCorrector = new QuotaCorrector(
                $positions->ranking(),
                $positions->bands(),
                $categories,
                $quota,
                $seats,
                (int) config('ballot.orderedlist_quota_max_nodes', QuotaCorrector::MAX_NODES),
            );
            $corrected = $quotaCorrector->result();
            $quotaBinding = $quota['binding'];
            $quotaWarnings = $quotaCorrector->warnings();
        }

        $warnings = [...$warnings, ...$quotaWarnings];

        // A binding quota's slate is the OFFICIAL result whenever it can be
        // applied at all -- even while a tie it genuinely depends on is still
        // open (D15): its certain seats are then official and the rest are
        // contested. Only an advisory or infeasible quota leaves the votes-
        // alone slate official.
        $official = ($corrected !== null && $quotaBinding && !$corrected['infeasible'])
            ? 'corrected'
            : 'natural';

        return new OrderedListResult(
            seats: $seats,
            ranking: $positions->ranking(),
            elected: $positions->elected(),
            bands: $positions->bands(),
            cutoffDecision: $positions->cutoffDecision(),
            corrected: $corrected,
            official: $official,
            beatpath: ['strength' => $schulze->strength(), 'winners' => $schulze->winners()],
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
     * Parse settings.quota defensively. Two shapes:
     *  - min/max: a string category, a min/max type, and an integer count
     *    (>= 1 for "min"; "max" additionally allows 0, meaning "exclude this
     *    category entirely"); binding defaults to true when absent or not a
     *    bool.
     *  - alternate: no category/count (the two groups are derived from
     *    settings.categories at correction time) -- only a boolean binding,
     *    defaulting to true. category/count are set to '' / 0 to keep the
     *    array shape (and its PHPStan annotation) identical across types.
     * Anything else is dropped with a warning rather than guessed at.
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

        $type = $raw['type'] ?? null;

        if ($type === 'alternate') {
            $binding = $raw['binding'] ?? null;

            return [
                'quota' => [
                    'category' => '',
                    'type' => 'alternate',
                    'count' => 0,
                    'binding' => is_bool($binding) ? $binding : true,
                ],
                'warning' => null,
            ];
        }

        $category = $raw['category'] ?? null;
        $count = $raw['count'] ?? null;
        $binding = $raw['binding'] ?? null;

        if (!is_string($category) || !is_string($type) || !in_array($type, ['min', 'max'], true) || !is_int($count) || ($type === 'max' ? $count < 0 : $count < 1)) {
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
