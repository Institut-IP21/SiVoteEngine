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
 * Schulze beatpath calculation classes over parsed/accounted ballots and
 * asserts the resulting OrderedListResult shape. Any genuine tie the votes
 * leave unresolved -- at the seat cutoff, in the order among already-elected
 * candidates, or inside a quota correction -- is SURFACED: the engine never
 * picks, and there is no resolution mechanism to apply one after the fact.
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

    // --- cutoff-tie-surfaced -------------------------------------------

    public function test_genuine_tie_at_the_seat_boundary_surfaces_a_cutoff_decision(): void
    {
        $c = $this->makeComponent(['A', 'B'], ['seats' => 1]);
        $votes = $this->votes($c, [['A'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertSame(1, $r['cutoff_decision']['remaining_seats']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['cutoff_decision']['candidates']);
        // The engine makes no pick of its own: nobody is elected, and the
        // tied pair carries no internal constraint (a genuine, symmetric tie).
        $this->assertSame([], $r['elected']);
        $this->assertCount(1, $r['bands']);
        $this->assertSame([], $r['bands'][0]['internal_constraints']);
    }

    /**
     * A 3-way genuine tie band at the seat cutoff (2x['A','C'] + 2x['B'])
     * reproduces the exact position-interval shape of
     * PositionResolverTest::test_contested_cutoff_seats_one (A[1,2]
     * contested, B[1,3] contested, C[2,3] excluded, one band {A,B,C} with
     * the locked fact A>C) but built from REAL ballots through
     * PairwiseMatrix -> SchulzeBeatpath -> PositionResolver. It is surfaced
     * as-is, with its one locked internal fact preserved: there is no
     * resolution mechanism to apply, and the engine makes no pick.
     */
    public function test_three_way_cutoff_tie_is_surfaced_with_its_locked_fact_preserved(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => 1]);
        $votes = $this->votes($c, [['A', 'C'], ['A', 'C'], ['B'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertSame([], $r['elected']);
        $this->assertCount(1, $r['bands']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['bands'][0]['candidates']);
        $this->assertSame([['winner' => 'A', 'loser' => 'C']], $r['bands'][0]['internal_constraints']);
    }

    // --- order-tie-surfaced ---------------------------------------------

    /**
     * A and B are tied 3-3 with each other but both decisively beat C: their
     * membership among the seats=2 winners is fully settled (both elected),
     * only their relative ORDER is a genuine tie -- so there is no cutoff
     * decision at all, only a band. This is a pure order-tie among already-
     * elected candidates, surfaced exactly like the cutoff case.
     */
    public function test_order_tie_among_already_elected_is_surfaced_without_a_cutoff_decision(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => 2]);
        $votes = $this->votes($c, [
            ...array_fill(0, 3, ['A', 'B']),
            ...array_fill(0, 3, ['B', 'A']),
            ['C'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertNull($r['cutoff_decision']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['elected']);
        $this->assertCount(1, $r['bands']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['bands'][0]['candidates']);
        $this->assertSame([1, 2], $r['bands'][0]['span']);
        $this->assertSame([], $r['bands'][0]['internal_constraints']);
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

    // --- quota-interacts-with-surfaced-tie: provisional/surfaced FOREVER,
    //     never auto-applied (no resolution mechanism exists) --------------

    /**
     * A binding quota whose only eligible promotion/demotion candidates fall
     * inside a genuinely tied (surfaced) band must stay PROVISIONAL: there
     * is no runner and no resolution mechanism that could ever pick one
     * member of a tied group over the other. `official` stays 'natural'
     * permanently -- this is not a transient/pending state, it is the
     * engine's final word absent the organization resolving the tie itself
     * (outside this engine, per its own rules).
     */
    public function test_binding_quota_touching_a_surfaced_tie_stays_provisional_forever(): void
    {
        $c = $this->makeComponent(
            ['A', 'B', 'C', 'D', 'E'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        // Membership of the top-3 is fully settled (A, B tied only in
        // ORDER; C alone; D/E tied below the cutoff) -- no cutoff decision.
        $this->assertNull($r['cutoff_decision']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['elected']);

        $this->assertSame('natural', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertTrue($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame(['A', 'B', 'C'], $r['corrected']['order']);
        $this->assertNotContains('D', $r['corrected']['order']);
        $this->assertNotContains('E', $r['corrected']['order']);
        $this->assertNotEmpty(array_filter(
            $r['warnings'],
            static fn (string $w): bool => str_contains($w, 'surfaced')
        ));
    }

    /**
     * Regression for the roster-order-dependency bug: swapping the tied
     * pair's roster position (E before D instead of D before E) must yield
     * a byte-identical provisional result -- proving there is no coin flip
     * hiding behind array order.
     */
    public function test_swapping_the_tied_pair_in_the_roster_yields_the_identical_surfaced_result(): void
    {
        $c = $this->makeComponent(
            ['A', 'B', 'C', 'E', 'D'],
            [
                'seats' => 3,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Eng', 'D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [['A', 'B', 'C', 'D'], ['B', 'A', 'C', 'E']]);

        $r = $this->calc($votes, $c);

        $this->assertSame('natural', $r['official']);
        $this->assertTrue($r['corrected']['provisional']);
        $this->assertSame(['A', 'B', 'C'], $r['corrected']['order']);
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

    // --- alternation ("zipper") quota, end-to-end through the full tally ---

    /**
     * Unanimous ballots settle a clean, fully-determined natural order
     * (A,B,C,D) that does NOT already alternate M,F,M,M. A binding
     * alternate quota must reorder it into the M,F,M,F... zipper pattern
     * (A,C,B,D) and become the official result — end-to-end through
     * PairwiseMatrix -> SchulzeBeatpath -> PositionResolver -> QuotaCorrector.
     */
    public function test_binding_alternate_quota_corrects_order_and_marks_official_corrected(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 4,
            'categories' => ['A' => 'M', 'B' => 'M', 'C' => 'F', 'D' => 'M'],
            'quota' => ['type' => 'alternate', 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame(['A', 'B', 'C', 'D'], $r['elected']);
        $this->assertSame('corrected', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C', 'B', 'D'], $r['corrected']['order']);
        // D13 finding #2: a within-top-K reorder now emits a diff row for
        // every candidate whose seated position differs from natural (B and
        // C swap), not only below-cut promotions -- `order` is unchanged.
        $this->assertSame(
            [
                ['candidate' => 'C', 'from' => 'natural:3', 'reason' => 'alternate'],
                ['candidate' => 'B', 'from' => 'natural:2', 'reason' => 'alternate'],
            ],
            $r['corrected']['diff']
        );
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertFalse($r['corrected']['provisional']);
    }

    /**
     * Same ballots/settings as above but advisory (binding:false): the
     * alternated order is still computed and reported, but `official` stays
     * 'natural' -- an advisory quota never overrides the votes-alone result.
     */
    public function test_advisory_alternate_quota_leaves_official_natural(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 4,
            'categories' => ['A' => 'M', 'B' => 'M', 'C' => 'F', 'D' => 'M'],
            'quota' => ['type' => 'alternate', 'binding' => false],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame('natural', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C', 'B', 'D'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['binding']);
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
        $this->assertNull($r['corrected']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame(['strength' => [], 'winners' => []], $r['beatpath']);
        $this->assertSame(['A', 'B', 'C'], $r['pairwise']['candidates']);
        $this->assertSame([], $r['pairwise']['matrix']);
    }

    /**
     * Distinct from test_empty_votes_returns_fully_formed_empty_shape: here
     * ballots WERE cast (cast > 0) but every one of them is blank or
     * invalid, so `counted` is still empty and the same fully-formed empty
     * shape is returned via the `counted === []` branch in
     * calculateResults -- but the accounting must reflect the real
     * cast/blank/invalid_only figures, not all zeroes.
     */
    public function test_votes_cast_but_all_blank_or_invalid_returns_empty_shape_with_nonzero_cast(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);

        $votes = [
            // blank: unanswered
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => null]),
            // blank: empty ranking
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => []]]),
            // blank: a scalar rather than a list
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => 'A']]),
            // invalid_only: single out-of-roster label
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z']]]),
            // invalid_only: only out-of-roster labels
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z', 'Q']]]),
        ];

        $r = $this->calc($votes, $c);

        // Hand-derived: 5 cast; blank = 3 (unanswered, empty ranking, scalar
        // value -- none of these is an array with entries); invalid_only = 2
        // (every label in each ranking is out of roster, so both collapse to
        // an empty "clean" list); counted = 5 - 3 - 2 = 0, so the
        // counted === [] branch fires despite cast > 0.
        $this->assertSame(5, $r['accounting']['cast']);
        $this->assertSame(3, $r['accounting']['blank']);
        $this->assertSame(2, $r['accounting']['invalid_only']);
        $this->assertSame(0, $r['accounting']['counted']);
        $this->assertSame(
            $r['accounting']['cast'],
            $r['accounting']['blank'] + $r['accounting']['invalid_only'] + $r['accounting']['counted']
        );

        // No winners/bands/order claimed -- the fully-formed empty shape.
        $this->assertSame([], $r['ranking']);
        $this->assertSame([], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertNull($r['corrected']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame(['strength' => [], 'winners' => []], $r['beatpath']);
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
        $this->assertSame('natural', $r['official']);
        $this->assertSame(['Solo'], $r['pairwise']['candidates']);
        $this->assertSame([], $r['pairwise']['matrix']);
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

    public function test_get_statute_text_returns_nonempty_bilingual_paragraphs(): void
    {
        $statute = $this->component->getStatuteText();

        $this->assertSame('OrderedList', $statute->type);
        $this->assertNotEmpty($statute->en);
        $this->assertNotEmpty($statute->sl);
        foreach ([...$statute->en, ...$statute->sl] as $paragraph) {
            $this->assertNotSame('', trim($paragraph));
        }
    }

    public function test_get_statute_text_never_names_stv_droop_or_surplus_transfer(): void
    {
        // FLAG 3 (statute-content-draft.md Notes §4): the composition
        // requirement is NOT a vote-based STV/Droop-quota mechanism — the
        // clause text must never suggest otherwise.
        $statute = $this->component->getStatuteText();
        $en = strtolower(implode(' ', $statute->en));
        $sl = strtolower(implode(' ', $statute->sl));

        foreach (['stv', 'droop', 'surplus transfer'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $en);
            $this->assertStringNotContainsString($forbidden, $sl);
        }
    }

    public function test_get_statute_text_cutoff_tie_never_affirms_casting_vote_or_random_draw(): void
    {
        $statute = $this->component->getStatuteText();
        $lower = strtolower(implode(' ', $statute->en));
        foreach ([
            'resolved by a casting vote', 'decided by a casting vote', 'broken by a casting vote',
            'resolved by a random draw', 'decided by a random draw', 'broken by a random draw',
        ] as $affirmation) {
            $this->assertStringNotContainsString($affirmation, $lower);
        }
    }

    public function test_get_academic_text_returns_populated_bilingual_content(): void
    {
        $academic = $this->component->getAcademicText();

        $this->assertSame('OrderedList', $academic->type);
        foreach (['en', 'sl'] as $locale) {
            $this->assertNotSame('', trim($academic->{$locale}['explanation']));
            $this->assertNotEmpty($academic->{$locale}['pros']);
            $this->assertNotEmpty($academic->{$locale}['cons']);
        }
        $this->assertNotSame($academic->en['explanation'], $academic->sl['explanation']);
    }
}
