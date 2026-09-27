<?php

declare(strict_types=1);

namespace App\Services;

use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\Support\ComponentRegistry;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use League\Csv\Writer;

/**
 * Service for ballot operations including result calculation and CSV export.
 */
final readonly class BallotService
{
    public function __construct(
        private ComponentRegistry $registry,
    ) {}

    /**
     * Get the component tree with metadata for all registered components.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getComponentTree(): array
    {
        $tree = [];

        foreach ($this->registry->all() as $type => $versions) {
            $tree[$type] = [];
            foreach (array_keys($versions) as $version) {
                $component = $this->registry->resolve($type, $version);
                $tree[$type][$version] = $component->getMetadata()->toArray();
            }
        }

        return $tree;
    }

    /**
     * Get the bilingual statute/legal-reference text for every registered
     * component type (D10/D11), parallel to `getComponentTree()` but served
     * through its own dedicated endpoint rather than folded into the
     * component tree (see statute-feature-spec.md §2.2/§2.4). Shape:
     * `{ "<Type>": {name:{en,sl}, method:{en,sl}, statute:{en:[...],sl:[...]},
     * academic:{en:{explanation,pros,cons}, sl:{...}}, lay:{en,sl}},
     * ..., quorum: {en:[...], sl:[...]} }` — `name`/`method` are joined in
     * from `components.php` in BOTH locales (D10, so the settings page's
     * switcher pills relabel on the locale toggle with no extra call);
     * `quorum` is the shared preamble (D11, from `statute.quorum`), a
     * top-level sibling of the per-type entries, not duplicated into each.
     * `academic` (the neutral explanation + pros/cons, from `getAcademicText()`)
     * and `lay` (the short voter-facing "how it works" copy, from
     * `components.<slug>.lay_explanation`) are purely additive — web_app
     * consumes them from this same endpoint instead of carrying its own
     * app-local academic.php.
     *
     * @return array<string, mixed>
     */
    public function getStatuteText(): array
    {
        $result = [];

        foreach ($this->registry->all() as $type => $versions) {
            // Only the current version's statute text is exposed — the
            // statute page describes the method as implemented today, not
            // every historical version.
            $version = array_key_first($versions);
            if ($version === null) {
                continue;
            }

            $component = $this->registry->resolve($type, $version);
            $slug = $this->typeSlug($type);
            $statute = $component->getStatuteText();
            $academic = $component->getAcademicText();

            $result[$type] = [
                'name' => [
                    'en' => $this->transString("components.{$slug}.name", 'en'),
                    'sl' => $this->transString("components.{$slug}.name", 'sl'),
                ],
                'method' => [
                    'en' => $this->transString("components.{$slug}.method", 'en'),
                    'sl' => $this->transString("components.{$slug}.method", 'sl'),
                ],
                'statute' => [
                    'en' => $statute->en,
                    'sl' => $statute->sl,
                ],
                'academic' => [
                    'en' => $academic->en,
                    'sl' => $academic->sl,
                ],
                'lay' => [
                    'en' => $this->transString("components.{$slug}.lay_explanation", 'en'),
                    'sl' => $this->transString("components.{$slug}.lay_explanation", 'sl'),
                ],
            ];
        }

        $result['quorum'] = [
            'en' => $this->transParagraphs('statute.quorum', 'en'),
            'sl' => $this->transParagraphs('statute.quorum', 'sl'),
        ];

        return $result;
    }

    /**
     * Map an engine type key (`YesNo`, `FirstPastThePost`, ...) to its
     * lowercase lang-file slug (`yesno`, `fptp`, ...) — the same slugs
     * `components.php` and `statute.php` are keyed by.
     */
    private function typeSlug(string $type): string
    {
        return match ($type) {
            'FirstPastThePost' => 'fptp',
            'RankedChoice' => 'rankedchoice',
            'ApprovalVote' => 'approval',
            'OrderedList' => 'orderedlist',
            default => 'yesno',
        };
    }

    /**
     * Fetch a scalar lang-file string in an explicit locale. `trans()`'s
     * static return type is `array|string`; this narrows it back to
     * `string` at runtime (a missing/array key degrades to '' rather than
     * throwing) so the caller's shape holds for PHPStan too.
     */
    private function transString(string $key, string $locale): string
    {
        $value = trans($key, [], $locale);
        return is_string($value) ? $value : '';
    }

    /**
     * Fetch an ordered list of clause paragraphs in an explicit locale —
     * same narrowing idiom as `AbstractBallotComponent::statuteParagraphs()`.
     *
     * @return list<string>
     */
    private function transParagraphs(string $key, string $locale): array
    {
        $value = trans($key, [], $locale);
        if (!is_array($value)) {
            return [];
        }

        $paragraphs = [];
        foreach ($value as $paragraph) {
            if (is_string($paragraph)) {
                $paragraphs[] = $paragraph;
            }
        }

        return $paragraphs;
    }

    /**
     * Get all available ballot types.
     *
     * @return array<string>
     */
    public function getBallotTypes(): array
    {
        return $this->registry->getTypes();
    }

    /**
     * Get available versions for a ballot type.
     *
     * @return array<string>
     */
    public function getBallotVersions(string $ballotType): array
    {
        return $this->registry->getVersions($ballotType);
    }

    /**
     * Get submission validators for all components in a ballot.
     *
     * @return array<string, array<mixed>>
     */
    public function getSubmissionValidators(Ballot $ballot): array
    {
        $validators = [];

        /** @var Election $election */
        $election = $ballot->election;

        foreach ($ballot->components as $componentModel) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $rules = $component->getSubmissionValidator($componentModel, $election);
            $validators = array_merge($validators, $rules->toArray());
        }

        return $validators;
    }

    /**
     * Get submission validators for only the submitted components.
     *
     * @param array<string, mixed> $params
     * @return array<string, array<mixed>>
     */
    public function getPartialSubmissionValidators(Ballot $ballot, array $params): array
    {
        $validators = [];

        /** @var Election $election */
        $election = $ballot->election;

        foreach ($ballot->components as $componentModel) {
            if (!array_key_exists($componentModel->id, $params)) {
                continue;
            }

            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $rules = $component->getSubmissionValidator($componentModel, $election);
            $validators = array_merge($validators, $rules->toArray());
        }

        return $validators;
    }

    /**
     * Get validators for a single component.
     *
     * @return array<string, array<mixed>>
     */
    public function getComponentValidators(BallotComponent $componentModel): array
    {
        $component = $this->registry->resolve($componentModel->type, $componentModel->version);
        /** @var Ballot $ballot */
        $ballot = $componentModel->ballot;
        /** @var Election $election */
        $election = $ballot->election;
        return $component->getSubmissionValidator($componentModel, $election)->toArray();
    }

    /**
     * Resolve a ballot component instance.
     */
    public function resolveComponent(string $type, string $version): BallotComponentInterface
    {
        return $this->registry->resolve($type, $version);
    }

    /**
     * Set each component's `order` to its position in the given id sequence.
     *
     * Only ids that actually belong to $ballot are applied — any id not on the
     * ballot (foreign, deleted, or bogus) is ignored, so a caller can never
     * touch another ballot's components through this path. Components on the
     * ballot whose id is absent from the sequence keep their current order.
     * Persistence is wrapped in a transaction so a partial reorder can't land.
     *
     * @param  array<int, string>  $orderedIds
     */
    public function reorderComponents(Ballot $ballot, array $orderedIds): void
    {
        /** @var array<string, BallotComponent> $owned */
        $owned = $ballot->components()->get()->keyBy('id')->all();

        DB::transaction(function () use ($orderedIds, $owned): void {
            $position = 0;
            foreach ($orderedIds as $id) {
                $component = $owned[$id] ?? null;
                if ($component === null) {
                    continue; // not on this ballot — ignore safely
                }
                $component->order = $position;
                $component->save();
                $position++;
            }
        });
    }

    /**
     * Calculate results for all components in a ballot.
     *
     * @return array<string, array<string, mixed>>
     */
    public function calculateResults(Ballot $ballot): array
    {
        $votes = collect($ballot->cast_votes);
        $abstainable = (bool) ($ballot->election->abstainable ?? false);
        $results = [];

        // D11: delegate quorum to the Ballot accessor (our canonical semantics).
        $results['_meta'] = [
            'quorum' => $ballot->quorum,
            'votes_cast' => $ballot->votes_count,
            'quorum_met' => $ballot->quorum_met,
        ];

        foreach ($ballot->components()->get() as $componentModel) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $result = $component->calculateResults($votes, $componentModel, $abstainable);

            $results[$componentModel->id] = [
                'results' => $result->toArray(),
                'title' => $componentModel->title,
                'description' => $componentModel->description,
                'type' => $componentModel->type,
            ];
        }

        return $results;
    }

    /**
     * Export ballot results to CSV format.
     */
    public function resultsCsv(Ballot $ballot): string
    {
        $votes = $ballot->castVotes();
        $components = $ballot->components()->get();

        $header = $components->pluck('title')->prepend(__('ballot.voteId'))->toArray();

        $resultsPerComponent = $components->map(function (BallotComponent $componentModel) use ($votes) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);

            return $votes->map(fn (Vote $vote): string =>
                $component->valuesToCsv($vote->values ?? [], $componentModel->id)
            );
        });

        $finalValues = $votes->pluck('id')->zip(...$resultsPerComponent);

        $csv = Writer::createFromString();
        $csv->insertOne($header);
        $csv->insertAll($finalValues->toArray());

        return $csv->getContent();
    }

    /**
     * Export the TALLIED outcome to CSV — one row per option/candidate per
     * question, computed from `calculateResults()` (not raw per-vote data like
     * `resultsCsv()`). Shared columns across every component type: question,
     * option, count, rate (%), elected (`yes`|`no`|`contested`), rank/seat.
     * A `contested` row means the engine genuinely could not decide between
     * options tied at the seat cutoff — it is never resolved into a fabricated
     * winner here.
     */
    public function resultsTallyCsv(Ballot $ballot): string
    {
        $results = $this->calculateResults($ballot);
        $components = $ballot->components()->get();

        $csv = Writer::createFromString();
        $csv->insertOne([
            __('ballot.tally_csv.question'),
            __('ballot.tally_csv.option'),
            __('ballot.tally_csv.count'),
            __('ballot.tally_csv.rate'),
            __('ballot.tally_csv.elected'),
            __('ballot.tally_csv.rank'),
        ]);

        foreach ($components as $componentModel) {
            $entry = $results[$componentModel->id] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $title = (string) ($entry['title'] ?? '');
            $type = (string) ($entry['type'] ?? '');
            $componentResults = is_array($entry['results'] ?? null) ? $entry['results'] : [];

            foreach ($this->tallyRowsForComponent($type, $componentResults) as $row) {
                $csv->insertOne([$title, $row['option'], $row['count'], $row['rate'], $row['elected'], $row['rank']]);
            }
        }

        return $csv->getContent();
    }

    /**
     * Build the tallied CSV rows for one component, shaped per its result DTO.
     * `elected` is always one of `yes`|`no`|`contested`; `count`/`rate`/`rank`
     * are left blank ('') where the component type has no matching concept.
     *
     * @param array<string, mixed> $results
     * @return list<array{option:string, count:int|string, rate:float|string, elected:string, rank:int|string}>
     */
    private function tallyRowsForComponent(string $type, array $results): array
    {
        return match ($type) {
            'ApprovalVote' => $this->approvalTallyRows($results),
            'OrderedList' => $this->orderedListTallyRows($results),
            'RankedChoice' => $this->rankedChoiceTallyRows($results),
            default => $this->singleWinnerTallyRows($results), // YesNo / FirstPastThePost
        };
    }

    /**
     * YesNo / FirstPastThePost: a flat `state` (option => count) plus a single
     * `winners` list. `winners` has more than one entry only on a genuine tie.
     *
     * @param array<string, mixed> $results
     * @return list<array{option:string, count:int, rate:float, elected:string, rank:string}>
     */
    private function singleWinnerTallyRows(array $results): array
    {
        $state = is_array($results['state'] ?? null) ? $results['state'] : [];
        $winners = array_map('strval', is_array($results['winners'] ?? null) ? $results['winners'] : []);
        $total = array_sum(array_map('intval', $state));

        $rows = [];
        foreach ($state as $option => $count) {
            $count = (int) $count;
            $option = (string) $option;
            $rate = $total > 0 ? round($count / $total * 100, 1) : 0.0;
            $elected = in_array($option, $winners, true)
                ? (count($winners) > 1 ? 'contested' : 'yes')
                : 'no';
            $rows[] = ['option' => $option, 'count' => $count, 'rate' => $rate, 'elected' => $elected, 'rank' => ''];
        }

        return $rows;
    }

    /**
     * ApprovalVote (post top-K): `state` gives per-option approval counts;
     * `elected`/`contested` give the seat-aware outcome. Elected options are
     * ranked (seat 1..K) in their `elected` display order (count-desc).
     *
     * @param array<string, mixed> $results
     * @return list<array{option:string, count:int, rate:float, elected:string, rank:int|string}>
     */
    private function approvalTallyRows(array $results): array
    {
        $state = is_array($results['state'] ?? null) ? $results['state'] : [];
        $voters = (int) ($results['voters'] ?? 0);
        $elected = array_map('strval', is_array($results['elected'] ?? null) ? $results['elected'] : []);
        $contested = array_map('strval', is_array($results['contested'] ?? null) ? $results['contested'] : []);

        $seatRank = [];
        foreach ($elected as $i => $option) {
            $seatRank[$option] = $i + 1;
        }

        $rows = [];
        foreach ($state as $option => $count) {
            $count = (int) $count;
            $option = (string) $option;
            $rate = $voters > 0 ? round($count / $voters * 100, 1) : 0.0;
            if (in_array($option, $elected, true)) {
                $electedFlag = 'yes';
            } elseif (in_array($option, $contested, true)) {
                $electedFlag = 'contested';
            } else {
                $electedFlag = 'no';
            }
            $rows[] = ['option' => $option, 'count' => $count, 'rate' => $rate, 'elected' => $electedFlag, 'rank' => $seatRank[$option] ?? ''];
        }

        return $rows;
    }

    /**
     * OrderedList: no per-candidate vote count in a Schulze tally — `ranking`
     * lists every candidate, `elected` is the seated slate, and a non-null
     * `cutoff_decision` names the candidates still contesting the last seat(s).
     *
     * @param array<string, mixed> $results
     * @return list<array{option:string, count:string, rate:string, elected:string, rank:int|string}>
     */
    private function orderedListTallyRows(array $results): array
    {
        $ranking = is_array($results['ranking'] ?? null) ? $results['ranking'] : [];
        $elected = array_map('strval', is_array($results['elected'] ?? null) ? $results['elected'] : []);
        $cutoffDecision = is_array($results['cutoff_decision'] ?? null) ? $results['cutoff_decision'] : null;
        $contested = $cutoffDecision !== null
            ? array_map('strval', is_array($cutoffDecision['candidates'] ?? null) ? $cutoffDecision['candidates'] : [])
            : [];

        $seatRank = [];
        foreach ($elected as $i => $candidate) {
            $seatRank[$candidate] = $i + 1;
        }

        $rows = [];
        foreach ($ranking as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $candidate = (string) ($entry['candidate'] ?? '');
            if ($candidate === '') {
                continue;
            }
            if (in_array($candidate, $elected, true)) {
                $electedFlag = 'yes';
            } elseif (in_array($candidate, $contested, true)) {
                $electedFlag = 'contested';
            } else {
                $electedFlag = 'no';
            }
            $rows[] = ['option' => $candidate, 'count' => '', 'rate' => '', 'elected' => $electedFlag, 'rank' => $seatRank[$candidate] ?? ''];
        }

        return $rows;
    }

    /**
     * RankedChoice: single-seat instant-runoff. `preferences` is a full-roster
     * option => [position => count] matrix (0-based positions); position 0 is
     * first-preference count, used here as the tally's `count` column.
     * `result.winners` is the conclusive winner (1 entry) or the tied labels
     * (non-conclusive, i.e. `contested`).
     *
     * @param array<string, mixed> $results
     * @return list<array{option:string, count:int, rate:float, elected:string, rank:string}>
     */
    private function rankedChoiceTallyRows(array $results): array
    {
        $preferences = is_array($results['preferences'] ?? null) ? $results['preferences'] : [];
        $result = is_array($results['result'] ?? null) ? $results['result'] : [];
        $winners = array_map('strval', is_array($result['winners'] ?? null) ? $result['winners'] : []);
        $conclusive = (bool) ($result['conclussive'] ?? false); // engine preserves the original typo

        $accounting = is_array($results['accounting'] ?? null) ? $results['accounting'] : [];
        $counted = (int) ($accounting['counted'] ?? 0);

        $rows = [];
        foreach ($preferences as $option => $positions) {
            $option = (string) $option;
            $firstPreferences = is_array($positions) ? (int) ($positions[0] ?? 0) : 0;
            $rate = $counted > 0 ? round($firstPreferences / $counted * 100, 1) : 0.0;
            if (in_array($option, $winners, true)) {
                $elected = $conclusive ? 'yes' : 'contested';
            } else {
                $elected = 'no';
            }
            $rows[] = ['option' => $option, 'count' => $firstPreferences, 'rate' => $rate, 'elected' => $elected, 'rank' => ''];
        }

        return $rows;
    }
}
