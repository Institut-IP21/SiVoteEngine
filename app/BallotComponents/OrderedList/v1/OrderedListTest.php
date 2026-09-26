<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Orchestration tests for the OrderedList component: wires the pure
 * Ranked-Pairs calculation classes (Tasks 1-6) over parsed/accounted
 * ballots and asserts the resulting OrderedListResult shape.
 */
class OrderedListTest extends TestCase
{
    private OrderedList $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new OrderedList();
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed> $settings
     */
    private function makeComponent(array $options, array $settings = []): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'OrderedList',
            'options' => $options,
            'settings' => $settings,
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed> $settings
     * @param list<array<string, mixed>> $resolutions
     */
    private function makeComponentWithResolutions(array $options, array $settings, array $resolutions): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'OrderedList',
            'options' => $options,
            'settings' => $settings,
            'runner_resolutions' => $resolutions,
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param list<list<string>> $rankings
     * @return array<int, Vote>
     */
    private function votes(BallotComponent $component, array $rankings): array
    {
        $votes = [];
        foreach ($rankings as $ranking) {
            $votes[] = Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => [$component->id => $ranking],
            ]);
        }
        return $votes;
    }

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    private function calc(array $votes, BallotComponent $component): array
    {
        return $this->component->calculateResults(new Collection($votes), $component)->toArray();
    }

    public function test_get_submission_validator_matches_ranked_choice_shape(): void
    {
        $election = Election::factory()->make();
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $validator = $this->component->getSubmissionValidator($component, $election)->toArray();

        $this->assertEquals([
            $component->id => ['required', 'array'],
            "$component->id.*" => ['distinct', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $validator);
    }

    public function test_clean_chain_elects_top_seats_with_no_cutoff(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], ['seats' => 2]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame(['A', 'B'], $r['elected']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame(5, $r['accounting']['counted']);
    }

    public function test_genuine_tie_at_the_seat_boundary_surfaces_a_cutoff_decision(): void
    {
        $c = $this->makeComponent(['A', 'B'], ['seats' => 1]);
        $votes = $this->votes($c, [['A'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertSame([], $r['elected']);
    }

    public function test_binding_min_quota_promotes_and_marks_official_corrected(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame('corrected', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertFalse($r['corrected']['provisional']);
    }

    public function test_advisory_non_binding_quota_leaves_official_natural(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => false],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame('natural', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C'], $r['corrected']['order']);
    }

    /**
     * Spec §6.7: a binding quota + a genuinely contested cutoff (all four
     * roster members solo-approved -> every pairwise comparison ties, one
     * band spans the whole roster) + a runner resolution that fully
     * determines the order. When the runner's chosen order does NOT satisfy
     * the quota, the quota must be re-applied over that resolved order (not
     * left deferred/provisional, and not left applied to the stale natural
     * ranking) so `corrected`/`official` reflect the promotion.
     */
    public function test_runner_resolution_violating_the_quota_is_corrected_after_the_reapply(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'D'],
            [
                'seats' => 2,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [[
                'cluster' => ['A', 'B', 'C', 'D'],
                // The runner's chosen order elects only Eng (A, B) -- it
                // violates the Sales >= 1 quota.
                'order' => ['A', 'B', 'C', 'D'],
                'comment' => 'Runner draw.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A'], ['B'], ['C'], ['D']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertNotNull($r['final']);
        $this->assertTrue($r['final']['complete']);

        $this->assertNotNull($r['corrected']);
        $this->assertFalse($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame(['A', 'C'], $r['corrected']['order']);
        $this->assertSame(
            [['candidate' => 'C', 'from' => 'below_cut', 'reason' => 'min_quota:Sales']],
            $r['corrected']['diff']
        );
        $this->assertSame('corrected', $r['official']);
    }

    /**
     * Mirror case: the runner's chosen order already satisfies the quota
     * (one Sales candidate lands in the top-2), so the re-applied correction
     * is a no-op diff, still official (binding + satisfied, not provisional).
     */
    public function test_runner_resolution_satisfying_the_quota_needs_no_correction(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'D'],
            [
                'seats' => 2,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [[
                'cluster' => ['A', 'B', 'C', 'D'],
                // C (Sales) lands in the top-2 already: the quota is satisfied.
                'order' => ['C', 'A', 'B', 'D'],
                'comment' => 'Runner draw.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A'], ['B'], ['C'], ['D']]);

        $r = $this->calc($votes, $c);

        $this->assertTrue($r['final']['complete']);
        $this->assertNotNull($r['corrected']);
        $this->assertFalse($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame(['C', 'A'], $r['corrected']['order']);
        $this->assertSame([], $r['corrected']['diff']);
        $this->assertSame('corrected', $r['official']);
    }

    /**
     * Regression for the vote-decisive quota re-run bug: a fully-"complete"
     * top-K (the runner resolved the ONLY blocking band, {A,B}) coexists
     * with an unresolved band entirely BELOW the cutoff ({D,E}, non-blocking
     * for completeness by design). A binding min-quota on D/E's category
     * must not promote either one by arbitrary roster/array order -- it has
     * to defer until the runner also settles that tie.
     */
    public function test_binding_quota_defers_rather_than_seating_a_tied_candidate(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'D', 'E'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [[
                'cluster' => ['A', 'B'],
                'order' => ['B', 'A'],
                'comment' => 'Coin toss.',
                'resolved_by' => 'returning-officer',
                'resolved_at' => '2026-09-26T10:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['final']);
        $this->assertTrue($r['final']['complete']);

        $this->assertSame('natural', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertTrue($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame(['B', 'A', 'C'], $r['corrected']['order']);
        $this->assertNotContains('D', $r['corrected']['order']);
        $this->assertNotContains('E', $r['corrected']['order']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'needs the runner')
        ));
    }

    /**
     * Same scenario with the tied pair's roster order flipped (E before D
     * instead of D before E). If the fix truly removes the roster-order
     * dependency, the deferred result must be byte-identical -- proving
     * there is no coin flip hiding behind array order.
     */
    public function test_swapping_the_tied_pair_in_the_roster_yields_the_identical_deferred_result(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'E', 'D'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [[
                'cluster' => ['A', 'B'],
                'order' => ['B', 'A'],
                'comment' => 'Coin toss.',
                'resolved_by' => 'returning-officer',
                'resolved_at' => '2026-09-26T10:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        $this->assertSame('natural', $r['official']);
        $this->assertTrue($r['corrected']['provisional']);
        $this->assertSame(['B', 'A', 'C'], $r['corrected']['order']);
    }

    /**
     * Once the runner ALSO settles the below-cutoff {D, E} tie, the binding
     * quota can safely promote D over the (now-resolved) natural order,
     * moving from deferred to officially corrected.
     */
    public function test_resolving_the_below_cutoff_tie_lets_the_binding_quota_apply(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'D', 'E'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [
                [
                    'cluster' => ['A', 'B'],
                    'order' => ['B', 'A'],
                    'comment' => 'Coin toss.',
                    'resolved_by' => 'returning-officer',
                    'resolved_at' => '2026-09-26T10:00:00Z',
                ],
                [
                    'cluster' => ['D', 'E'],
                    'order' => ['D', 'E'],
                    'comment' => 'Runner draw.',
                    'resolved_by' => 'returning-officer',
                    'resolved_at' => '2026-09-26T10:05:00Z',
                ],
            ],
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        $this->assertSame('corrected', $r['official']);
        $this->assertSame(['B', 'A', 'D'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['provisional']);
        $this->assertSame(
            [['candidate' => 'D', 'from' => 'below_cut', 'reason' => 'min_quota:Sales']],
            $r['corrected']['diff']
        );
    }

    /**
     * FIX 2: the first run's quota-deferral warning is superseded once the
     * re-run resolves the quota; it must not survive alongside the final
     * "corrected" result and contradict it.
     */
    public function test_the_first_run_quota_deferral_warning_does_not_survive_the_reapply(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C', 'D', 'E'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
            [
                [
                    'cluster' => ['A', 'B'],
                    'order' => ['B', 'A'],
                    'comment' => 'Coin toss.',
                    'resolved_by' => 'returning-officer',
                    'resolved_at' => '2026-09-26T10:00:00Z',
                ],
                [
                    'cluster' => ['D', 'E'],
                    'order' => ['D', 'E'],
                    'comment' => 'Runner draw.',
                    'resolved_by' => 'returning-officer',
                    'resolved_at' => '2026-09-26T10:05:00Z',
                ],
            ],
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        $this->assertSame('corrected', $r['official']);
        $this->assertEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'quota deferred') || str_contains($w, 'needs the runner')
        ));
    }

    public function test_seats_clamp_and_malformed_quota_warnings_appear_in_the_dto(): void
    {
        $c = $this->makeComponent(['A', 'B'], [
            'seats' => 5,
            'quota' => ['category' => 'Sales', 'type' => 'min'],
        ]);
        $votes = $this->votes($c, [['A', 'B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotEmpty(array_filter($r['warnings'], static fn (string $w): bool => str_contains($w, 'seats clamped')));
        $this->assertNotEmpty(array_filter($r['warnings'], static fn (string $w): bool => str_contains($w, 'quota settings malformed')));
    }

    public function test_max_quota_count_zero_is_accepted(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Sales', 'B' => 'Sales', 'C' => 'Eng', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'max', 'count' => 0, 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['corrected']);
        $this->assertSame(['C', 'D'], $r['corrected']['order']);
        $this->assertSame('corrected', $r['official']);
    }

    public function test_empty_votes_returns_fully_formed_empty_shape(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);

        $r = $this->calc([], $c);

        $this->assertSame(0, $r['accounting']['cast']);
        $this->assertSame(0, $r['accounting']['blank']);
        $this->assertSame(0, $r['accounting']['invalid_only']);
        $this->assertSame(0, $r['accounting']['counted']);
        $this->assertSame([], $r['ranking']);
        $this->assertSame([], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertSame([], $r['resolutions']);
        $this->assertNull($r['final']);
        $this->assertNull($r['corrected']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame([], $r['lock_in_log']);
        $this->assertSame(['A', 'B', 'C'], $r['pairwise']['candidates']);
        $this->assertSame([], $r['pairwise']['matrix']);
    }

    public function test_accounting_reconciles_blank_invalid_and_duplicate_ballots(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);

        $votes = [
            // unanswered
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => null]),
            // blank: empty ranking
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => []]]),
            // blank: a scalar rather than a list
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => 'A']]),
            // invalid_only: single out-of-roster label
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z']]]),
            // invalid_only: only out-of-roster labels
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z', 'Q']]]),
            // counted: duplicate label collapses to one entry
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['A', 'A']]]),
            // counted: normal ranking
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['A', 'B']]]),
        ];

        $r = $this->calc($votes, $c);
        $acc = $r['accounting'];

        $this->assertSame(7, $acc['cast']);
        $this->assertSame(3, $acc['blank']);
        $this->assertSame(2, $acc['invalid_only']);
        $this->assertSame(2, $acc['counted']);
        $this->assertSame($acc['cast'], $acc['blank'] + $acc['invalid_only'] + $acc['counted']);
    }

    public function test_values_to_csv_joins_ranked_labels_in_order(): void
    {
        $this->assertSame('B, A', $this->component->valuesToCsv(['cid' => ['B', 'A']], 'cid'));
        $this->assertSame('', $this->component->valuesToCsv([], 'cid'));
    }

    public function test_values_to_csv_scalar_branch_casts_to_string(): void
    {
        $this->assertSame('A', $this->component->valuesToCsv(['cid' => 'A'], 'cid'));
        $this->assertSame('1', $this->component->valuesToCsv(['cid' => 1], 'cid'));
    }

    /**
     * KNOWN v1 LIMITATION (do not fix — out of scope): a candidate label
     * containing a comma is indistinguishable, once CSV-joined with ', ',
     * from two separate ranked labels. This pins the CURRENT (naive-join)
     * behavior so a future change to valuesToCsv is caught as a deliberate
     * decision, not a silent regression.
     */
    public function test_comma_in_label_csv_ambiguity_is_a_known_v1_limitation(): void
    {
        $csv = $this->component->valuesToCsv(['cid' => ['Smith, John', 'Doe, Jane']], 'cid');

        // Indistinguishable from ranking four plain labels
        // ['Smith', ' John', 'Doe', ' Jane'] — a known v1 limitation.
        $this->assertSame('Smith, John, Doe, Jane', $csv);
    }

    // --- Edge case: seats <= 0 (lower-bound clamp direction) ---------------
    // The existing test_seats_clamp_and_malformed_quota_warnings_appear_in_the_dto
    // only exercises the UPPER clamp (min($n, seats)); these pin the LOWER
    // clamp (max(1, seats)), which already guards seats<=0 correctly.

    public function test_seats_zero_clamps_to_one_with_warning(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => 0]);
        $votes = $this->votes($c, [['A', 'B', 'C']]);

        $r = $this->calc($votes, $c);

        $this->assertSame(1, $r['seats']);
        $this->assertSame(['A'], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'seats clamped to 1 (requested 0, roster has 3)')
        ));
    }

    public function test_seats_negative_clamps_to_one_with_warning(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => -5]);
        $votes = $this->votes($c, [['A', 'B', 'C']]);

        $r = $this->calc($votes, $c);

        $this->assertSame(1, $r['seats']);
        $this->assertSame(['A'], $r['elected']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'seats clamped to 1 (requested -5, roster has 3)')
        ));
    }

    // --- Edge case: a foreign (non-roster) candidate id on a ballot --------

    /**
     * Vote-integrity edge: a ballot ranks a stale/foreign candidate id mixed
     * in among real preferences. It must be silently ignored -- not seated,
     * not counted as a real preference, and unable to leak into the
     * pairwise matrix or perturb the tally over the real roster.
     */
    public function test_foreign_candidate_in_a_ballot_is_ignored_and_cannot_influence_the_tally(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => 2]);
        $withForeign = $this->votes($c, [
            ['A', 'Ghost', 'B'],
            ['A', 'Ghost', 'B'],
            ['C'],
        ]);
        $withoutForeign = $this->votes($c, [
            ['A', 'B'],
            ['A', 'B'],
            ['C'],
        ]);

        $rWithForeign = $this->calc($withForeign, $c);
        $rWithoutForeign = $this->calc($withoutForeign, $c);

        $this->assertNotContains('Ghost', $rWithForeign['pairwise']['candidates']);
        $this->assertArrayNotHasKey('Ghost', $rWithForeign['pairwise']['matrix']);
        foreach ($rWithForeign['ranking'] as $entry) {
            $this->assertNotSame('Ghost', $entry['candidate']);
        }

        // Stripping the foreign label from every ballot must yield an
        // identical tally: the foreign id can neither help nor hurt any
        // real candidate's result.
        $this->assertSame($rWithoutForeign['pairwise']['matrix'], $rWithForeign['pairwise']['matrix']);
        $this->assertSame($rWithoutForeign['elected'], $rWithForeign['elected']);
        $this->assertSame($rWithoutForeign['ranking'], $rWithForeign['ranking']);
    }

    // --- Edge case: N = 1 (single-candidate roster) -------------------------

    public function test_single_candidate_roster_is_elected_outright(): void
    {
        $c = $this->makeComponent(['Solo'], ['seats' => 1]);
        $votes = $this->votes($c, [['Solo'], ['Solo']]);

        $r = $this->calc($votes, $c);

        $this->assertSame(1, $r['seats']);
        $this->assertSame(['Solo'], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertNull($r['final']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame(['Solo'], $r['pairwise']['candidates']);
        $this->assertSame([], $r['pairwise']['matrix']);
    }

    // --- Edge case: a genuine 3-member tie band + a full 3-permutation -----

    /**
     * 2x['A','C'] + 2x['B'] end-to-end reproduces the exact position-interval
     * shape of PositionResolverTest::test_contested_cutoff_seats_one
     * (A[1,2] contested, B[1,3] contested, C[2,3] excluded, one band {A,B,C}
     * with the locked fact A>C) but built from REAL ballots through
     * PairwiseMatrix -> RankedPairsLock -> PositionResolver, not synthetic
     * band data. A full 3-permutation resolution that respects the locked
     * A>C fact is accepted and completes the result.
     */
    public function test_three_way_tie_band_accepts_a_full_three_permutation_resolution(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C'],
            ['seats' => 1],
            [[
                'cluster' => ['A', 'B', 'C'],
                'order' => ['B', 'A', 'C'],
                'comment' => 'Runner draw, all three tied.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A', 'C'], ['A', 'C'], ['B'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertCount(1, $r['bands']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['bands'][0]['candidates']);
        $this->assertSame([['winner' => 'A', 'loser' => 'C']], $r['bands'][0]['internal_constraints']);

        $this->assertNotNull($r['final']);
        $this->assertTrue($r['final']['complete']);
        $this->assertSame(
            [
                ['position' => 1, 'candidate' => 'B', 'tied' => false],
                ['position' => 2, 'candidate' => 'A', 'tied' => false],
                ['position' => 3, 'candidate' => 'C', 'tied' => false],
            ],
            $r['final']['order']
        );
    }

    /**
     * Same 3-way band; a full 3-permutation that DOES respect the "is a
     * permutation of the tied set" rule but contradicts the locked A>C
     * pairwise fact (placing C ahead of A) must be rejected outright, not
     * partially applied.
     */
    public function test_three_permutation_contradicting_a_locked_pair_is_rejected(): void
    {
        $c = $this->makeComponentWithResolutions(
            ['A', 'B', 'C'],
            ['seats' => 1],
            [[
                'cluster' => ['A', 'B', 'C'],
                'order' => ['C', 'B', 'A'],
                'comment' => 'Bad draw.',
                'resolved_by' => 'chair@org',
                'resolved_at' => '2026-09-26T20:00:00Z',
            ]],
        );
        $votes = $this->votes($c, [['A', 'C'], ['A', 'C'], ['B'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNull($r['final']);
        $this->assertSame([], $r['elected']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'contradicts a locked head-to-head result')
        ));
    }

    // --- Edge case: quota count vs. seats bounds (end-to-end) ---------------

    /**
     * min count(3) > seats(2), even though the category has 3 members
     * SOMEWHERE in the roster (not a category-scarcity infeasibility) --
     * structurally impossible since only 2 seats exist. Must report
     * infeasible, not crash or guess.
     */
    public function test_min_quota_count_greater_than_seats_is_infeasible_end_to_end(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D', 'E'], [
            'seats' => 2,
            'categories' => ['C' => 'Sales', 'D' => 'Sales', 'E' => 'Sales'],
            'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 3, 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D', 'E']));

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['corrected']);
        $this->assertTrue($r['corrected']['infeasible']);
        $this->assertSame(['A', 'B'], $r['corrected']['order']);
        $this->assertSame('natural', $r['official']);
    }

    /**
     * max count(2) == seats(2) can never bind (n can never exceed seats) --
     * trivially satisfied, natural order unchanged, no diff.
     */
    public function test_max_quota_count_at_seats_end_to_end_is_trivially_satisfied(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Sales', 'B' => 'Sales', 'C' => 'Eng', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'max', 'count' => 2, 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['corrected']);
        $this->assertSame([], $r['corrected']['diff']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame(['A', 'B'], $r['corrected']['order']);
    }

    // --- Edge case: unicode candidate labels --------------------------------

    public function test_unicode_candidate_labels_flow_through_pairwise_and_csv_intact(): void
    {
        $c = $this->makeComponent(['Ámbar', '候选人', 'Zoë'], ['seats' => 2]);
        $votes = $this->votes($c, array_fill(0, 3, ['Ámbar', '候选人', 'Zoë']));

        $r = $this->calc($votes, $c);

        $this->assertSame(['Ámbar', '候选人'], $r['elected']);
        $this->assertSame(['Ámbar', '候选人', 'Zoë'], $r['pairwise']['candidates']);
        $this->assertArrayHasKey('Ámbar', $r['pairwise']['matrix']);
        $this->assertArrayHasKey('候选人', $r['pairwise']['matrix']['Ámbar']);

        $csv = $this->component->valuesToCsv(['cid' => ['Ámbar', '候选人']], 'cid');
        $this->assertSame('Ámbar, 候选人', $csv);
    }

    // --- Edge case: duplicate candidate labels in the roster ----------------

    /**
     * VOTE-INTEGRITY DEFECT FOUND AND FIXED (see OrderedList::calculateResults):
     * a roster containing a duplicate candidate label (bypassing the
     * builder's `distinct` option validation -- e.g. options set via a
     * different path) made PositionResolver emit the SAME candidate as two
     * separate ranking rows, both independently eligible for 'elected'
     * status -- silently squeezing a real, distinct candidate out of the
     * seat count (with seats=2 here, `elected` would have come back
     * ['A', 'A'] instead of ['A', 'B'], dropping B). The minimal fix dedupes
     * the roster (first occurrence wins) with a warning before any
     * tabulation runs.
     */
    public function test_duplicate_candidate_labels_in_the_roster_are_deduped_not_double_counted(): void
    {
        $c = $this->makeComponent(['A', 'A', 'B'], ['seats' => 2]);
        $votes = $this->votes($c, [['A', 'B'], ['A', 'B'], ['B', 'A']]);

        $r = $this->calc($votes, $c);

        $this->assertSame(['A', 'B'], $r['pairwise']['candidates']);
        $this->assertSame(2, $r['seats']);
        $this->assertCount(2, $r['ranking']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['elected']);
        $this->assertCount(2, $r['elected']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'duplicate candidate labels')
        ));
    }
}
