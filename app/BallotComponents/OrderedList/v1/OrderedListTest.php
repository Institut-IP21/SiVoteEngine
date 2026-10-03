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
        $this->assertSame(['A', 'C'], $r['official_order']);
        $this->assertTrue($r['final']);
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
        $this->assertFalse($r['final']);
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

    // --- Alternation vs. a contested cutoff (prod bug 2026-10-03) ---------

    /**
     * Prod regression (ballot 23bc3e44, 2026-10-03): natural Schulze order
     * F1 > M1 > M2 > {M3 = F2} > F3 with 4 seats -- the cut is contested,
     * but only ACROSS the two groups. A zipper only ever consumes each
     * group's OWN order (F: F1 > F2 > F3, M: M1 > M2 > M3, both strict), so
     * the alternated slate is fully determined: F1, M1, F2, M2. It used to
     * short-circuit on the contested cut and report the natural elected
     * prefix F1, M1, M2 (two Ms back to back) under the "with alternation"
     * heading.
     */
    public function test_alternate_resolves_a_cross_group_tie_at_the_cutoff(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            [
                'seats' => 4,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            ['F1', 'M1', 'M2', 'F2', 'M3', 'F3'],
        ]);

        $r = $this->calc($votes, $c);

        // The natural cut really is contested (M3 vs F2 for seat 4).
        $this->assertNotNull($r['cutoff_decision']);
        $this->assertEqualsCanonicalizing(['M3', 'F2'], $r['cutoff_decision']['candidates']);

        $this->assertSame(['F1', 'M1', 'F2', 'M2'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame('corrected', $r['official']);
        // The official slate, and finality, are the alternated ones -- the
        // natural cut tie no longer holds the result open.
        $this->assertSame(['F1', 'M1', 'F2', 'M2'], $r['official_order']);
        $this->assertTrue($r['final']);
        $this->assertSame([], array_filter($r['warnings'], static fn (string $w): bool => str_contains($w, 'surfaced')));
    }

    /**
     * Same profile, tied pair swapped in the roster: identical result -- no
     * array-order coin flip.
     */
    public function test_alternate_cross_group_cutoff_tie_is_roster_order_independent(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'M2', 'F2', 'M3', 'F3'],
            [
                'seats' => 4,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            ['F1', 'M1', 'M2', 'F2', 'M3', 'F3'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertSame(['F1', 'M1', 'F2', 'M2'], $r['corrected']['order']);
        $this->assertSame('corrected', $r['official']);
    }

    /**
     * A SAME-group tie the zipper actually has to consume stays surfaced:
     * {F2 = F3} both compete for the second F seat. The determined prefix
     * (F1, M1) is reported; nothing past the first undetermined seat is.
     */
    public function test_alternate_same_group_tie_at_a_needed_seat_surfaces_only_the_determined_prefix(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'M2', 'F2', 'F3', 'M3'],
            [
                'seats' => 4,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'M2', 'F2', 'F3', 'M3'],
            ['F1', 'M1', 'M2', 'F3', 'F2', 'M3'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertTrue($r['corrected']['provisional']);
        $this->assertSame(['F1', 'M1'], $r['corrected']['order']);
        $this->assertSame('natural', $r['official']);
        $this->assertFalse($r['final']);
    }

    /**
     * A cross-group ORDER tie inside the top-K (no contested cut) does not
     * block the zipper either: {M1 = F2} tied at 2-3, zipper F1, M1, F2, M2.
     */
    public function test_alternate_cross_group_order_tie_inside_top_k_is_resolved(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'F2', 'M2', 'F3'],
            [
                'seats' => 4,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'F2', 'M2', 'F3'],
            ['F1', 'F2', 'M1', 'M2', 'F3'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertNull($r['cutoff_decision']);
        $this->assertSame(['F1', 'M1', 'F2', 'M2'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['provisional']);
        $this->assertSame('corrected', $r['official']);
    }

    /**
     * A tie for the natural #1 spot ACROSS groups makes the start group
     * itself undecided: surface, report nothing.
     */
    public function test_alternate_cross_group_tie_for_first_place_surfaces_with_empty_prefix(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'F2', 'M2'],
            [
                'seats' => 2,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'M1' => 'M', 'M2' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'F2', 'M2'],
            ['M1', 'F1', 'F2', 'M2'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertTrue($r['corrected']['provisional']);
        $this->assertSame([], $r['corrected']['order']);
        $this->assertSame('natural', $r['official']);
    }

    /**
     * Contested cut where the tie decides WHICH groups the zipper uses: the
     * natural top-2 is {F1} + one of {M1 (M), X1 (untagged)}. If M1 takes
     * the seat the slate is F1, M1; if X1 does, an untagged front-runner
     * makes the zipper infeasible. Must surface, not guess.
     */
    public function test_alternate_contested_cut_that_changes_the_group_set_surfaces(): void
    {
        $c = $this->makeComponent(
            ['F1', 'M1', 'X1', 'F2'],
            [
                'seats' => 2,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'M1' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );
        $votes = $this->votes($c, [
            ['F1', 'M1', 'X1', 'F2'],
            ['F1', 'X1', 'M1', 'F2'],
        ]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertTrue($r['corrected']['provisional']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertSame('natural', $r['official']);
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

    public function test_get_manual_steps_returns_populated_bilingual_ordered_steps(): void
    {
        $manual = $this->component->getManualSteps();

        $this->assertSame('OrderedList', $manual->type);
        $this->assertNotEmpty($manual->en);
        $this->assertNotEmpty($manual->sl);
        foreach ([...$manual->en, ...$manual->sl] as $step) {
            $this->assertNotSame('', trim($step));
        }
        $this->assertNotSame($manual->en, $manual->sl);
    }

    public function test_get_method_comparison_returns_the_approved_ratings_and_elected_descriptor(): void
    {
        $comparison = $this->component->getMethodComparison();

        $this->assertSame('OrderedList', $comparison->type);
        $this->assertSame(['true_prefs' => 5, 'manipulation' => 4, 'simplicity' => 2], $comparison->ratings);
        $this->assertSame('Multiple, ranked (K)', $comparison->elected['en']);
        $this->assertSame('Več, razvrščeni (K)', $comparison->elected['sl']);
    }

    // --- Gap-closing coverage (test-quality review, 2026-09-27) -------------

    /**
     * [MOST IMPORTANT GAP] A genuine 4-candidate Condorcet CYCLE built from
     * REAL ballots (not injected decisive-pairs), run through the full
     * PairwiseMatrix -> SchulzeBeatpath -> PositionResolver pipeline via the
     * component's calculate path. Exercises the one property that justifies
     * choosing Schulze over Copeland/plain-pairwise: an INDIRECT beatpath
     * overturning a DIRECT pairwise defeat. Every expected value below was
     * HAND-DERIVED (never read off a test run) -- see the derivation.
     *
     * -- Ballot profile (4 blocs of full rankings; every voter approves and
     *    ranks all 4 candidates) -----------------------------------------
     *   bloc 1 (3 voters): A, B, C, D
     *   bloc 2 (4 voters): B, C, D, A
     *   bloc 3 (5 voters): C, D, A, B
     *   bloc 4 (6 voters): D, A, B, C
     *   total = 18 ballots, all fully ranked (counted = 18, blank = 0,
     *   invalid_only = 0).
     *
     * -- prefers[x][y]: for every bloc and every ordered pair, does x
     *    precede y in that bloc's ranking? (Every ballot is a total order,
     *    so every one of the 18 ballots decides every pair; the two totals
     *    for any pair must always sum to 18.) --------------------------
     *   A,B: A-before-B in blocs 1,3,4 (3+5+6=14); B-before-A in bloc 2 (4).
     *     prefers[A][B]=14, prefers[B][A]=4   -> A beats B, margin 10
     *   A,C: A-before-C in blocs 1,4 (3+6=9);  C-before-A in blocs 2,3 (4+5=9).
     *     prefers[A][C]=9,  prefers[C][A]=9   -> TIE, no decisive edge
     *   A,D: A-before-D in bloc 1 (3); D-before-A in blocs 2,3,4 (4+5+6=15).
     *     prefers[A][D]=3,  prefers[D][A]=15  -> D beats A, margin 12
     *   B,C: B-before-C in blocs 1,2,4 (3+4+6=13); C-before-B in bloc 3 (5).
     *     prefers[B][C]=13, prefers[C][B]=5   -> B beats C, margin 8
     *   B,D: B-before-D in blocs 1,2 (3+4=7); D-before-B in blocs 3,4 (5+6=11).
     *     prefers[B][D]=7,  prefers[D][B]=11  -> D beats B, margin 4
     *   C,D: C-before-D in blocs 1,2,3 (3+4+5=12); D-before-C in bloc 4 (6).
     *     prefers[C][D]=12, prefers[D][C]=6   -> C beats D, margin 6 (DIRECT)
     *
     *   Decisive edges (winner -> loser, margin): A->B(10), D->A(12),
     *   B->C(8), D->B(4), C->D(6). This is a genuine 4-cycle in the direct
     *   pairwise graph -- A beats B, B beats C, C beats D, D beats A -- no
     *   total order is consistent with all four relations at once, and every
     *   margin is distinct (not a symmetric tie).
     *
     * -- Strongest-path (widest-path) matrix, HAND-COMPUTED. Each node's
     *    outgoing decisive edge(s) fix every path leaving it, so every
     *    p[i][j] can be read off by enumerating the (few) simple paths --
     *    A's only out-edge is ->B, B's only out-edge is ->C, C's only
     *    out-edge is ->D, D has two out-edges (->A and ->B): ---------------
     *   From A (A->B=10): p[A][B]=10; p[A][C]=min(10,8)=8 (via B);
     *     p[A][D]=min(10,8,6)=6 (via B,C).
     *   From B (B->C=8): p[B][C]=8; p[B][D]=min(8,6)=6 (via C);
     *     p[B][A]=min(8,6,12)=6 (via C,D).
     *   From C (C->D=6): p[C][D]=6; p[C][A]=min(6,12)=6 (via D);
     *     p[C][B]=max(min(6,4), min(6,12,10))=max(4,6)=6 -- two routes via D
     *     (C->D->B=4, or the longer C->D->A->B=6); widest path takes the max.
     *   From D (D->A=12, D->B=4): p[D][A]=12 (direct, nothing stronger
     *     reaches A); p[D][B]=max(4, min(12,10))=max(4,10)=10 (the indirect
     *     D->A->B route, strength 10, beats the direct D->B edge, strength
     *     4); p[D][C]=max(min(4,8), min(12,10,8))=max(4,8)=8 (the indirect
     *     D->A->B->C route, strength 8, beats the indirect D->B->C route,
     *     strength 4).
     *
     *   Full matrix: A={B:10,C:8,D:6}   B={A:6,C:8,D:6}
     *                C={A:6,B:6,D:6}    D={A:12,B:10,C:8}
     *
     * -- THE OVERTURN (the property this test exists to prove): direct
     *    pairwise has C beat D head-to-head, 12 votes to 6 (margin 6). But
     *    the strongest-path matrix has p[D][C]=8 > p[C][D]=6 -- D's
     *    INDIRECT beatpath to C (D->A->B->C, whose weakest link is B->C=8)
     *    is strictly stronger than C's DIRECT edge to D (6). The Schulze
     *    relation therefore FLIPS this pair: D outranks C even though C beat
     *    D head-to-head.
     *
     * -- reachable() (i outranks j iff p[i][j] > p[j][i]) -------------------
     *   A>B (10>6), A>C (8>6), D>A (12>6), B>C (8>6), D>B (10>6), D>C (8>6).
     *   D beats everyone; A beats B,C; B beats C; C beats nobody.
     *   -> strict total order: D > A > B > C (fully determined, no bands,
     *      no cutoff decision).
     *
     * -- Copeland comparison (win-count over the DIRECT pairwise relations
     *    only: A>B, D>A, B>C, D>B, C>D; A-C tied, no win either way) --------
     *   Copeland(A)=1 (beats B only)      Copeland(B)=1 (beats C only)
     *   Copeland(C)=1 (beats D only)      Copeland(D)=2 (beats A and B)
     *   Copeland ranks D first, then leaves A, B and C in a THREE-WAY TIE
     *   (all score 1) -- it cannot see that A indirectly dominates B and C,
     *   or that B indirectly dominates C, or that D indirectly dominates C
     *   via a stronger beatpath than C's own direct win. Schulze alone
     *   resolves the full strict order D > A > B > C; Copeland genuinely
     *   fails to (this is NOT the strict order Schulze produces).
     *
     * -- seats=2: elected = top 2 of D > A > B > C = {D, A}. ----------------
     */
    public function test_genuine_cyclic_ballot_profile_resolves_via_beatpath_overturning_a_direct_defeat(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], ['seats' => 2]);
        $rankings = [
            ...array_fill(0, 3, ['A', 'B', 'C', 'D']),
            ...array_fill(0, 4, ['B', 'C', 'D', 'A']),
            ...array_fill(0, 5, ['C', 'D', 'A', 'B']),
            ...array_fill(0, 6, ['D', 'A', 'B', 'C']),
        ];
        $votes = $this->votes($c, $rankings);

        $r = $this->calc($votes, $c);

        $this->assertSame(18, $r['accounting']['cast']);
        $this->assertSame(0, $r['accounting']['blank']);
        $this->assertSame(0, $r['accounting']['invalid_only']);
        $this->assertSame(18, $r['accounting']['counted']);

        $this->assertSame(['A', 'B', 'C', 'D'], $r['pairwise']['candidates']);
        $this->assertEquals([
            'A' => ['B' => 14, 'C' => 9, 'D' => 3],
            'B' => ['A' => 4, 'C' => 13, 'D' => 7],
            'C' => ['A' => 9, 'B' => 5, 'D' => 12],
            'D' => ['A' => 15, 'B' => 11, 'C' => 6],
        ], $r['pairwise']['matrix']);

        $this->assertEquals([
            'A' => ['B' => 10, 'C' => 8, 'D' => 6],
            'B' => ['A' => 6, 'C' => 8, 'D' => 6],
            'C' => ['A' => 6, 'B' => 6, 'D' => 6],
            'D' => ['A' => 12, 'B' => 10, 'C' => 8],
        ], $r['beatpath']['strength']);

        // The overturn, asserted directly: DIRECT pairwise has C beat D
        // (12 vs 6) but the beatpath STRENGTH has D's indirect path to C (8)
        // beat C's direct path to D (6) -- the Schulze relation flips this
        // pair relative to the raw pairwise result.
        $this->assertGreaterThan($r['pairwise']['matrix']['D']['C'], $r['pairwise']['matrix']['C']['D']);
        $this->assertGreaterThan($r['beatpath']['strength']['C']['D'], $r['beatpath']['strength']['D']['C']);

        $this->assertSame([
            ['candidate' => 'D', 'best_pos' => 1, 'worst_pos' => 1, 'determined' => true, 'status' => 'elected'],
            ['candidate' => 'A', 'best_pos' => 2, 'worst_pos' => 2, 'determined' => true, 'status' => 'elected'],
            ['candidate' => 'B', 'best_pos' => 3, 'worst_pos' => 3, 'determined' => true, 'status' => 'excluded'],
            ['candidate' => 'C', 'best_pos' => 4, 'worst_pos' => 4, 'determined' => true, 'status' => 'excluded'],
        ], $r['ranking']);
        $this->assertSame(['D', 'A'], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertSame('natural', $r['official']);
    }

    /**
     * D12: `OrderedList::calculateResults` defaults `seats` to
     * `count($roster)` when the setting is absent (`$settings['seats'] ?? $n`)
     * -- locks this against a regression to `?? 1`. A clean, acyclic,
     * unanimous chain with NO `seats` setting at all must elect every option
     * on the roster.
     */
    public function test_seats_default_with_no_setting_is_all_options(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C']));

        $r = $this->calc($votes, $c);

        $this->assertSame(3, $r['seats']);
        $this->assertSame(count($c->options), $r['seats']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['elected']);
        $this->assertCount(3, $r['elected']);
    }

    /**
     * Blank-ballot immunity, asserted directly on the pairwise MATRIX (and
     * on ranking/elected/bands) -- not merely inferred from the accounting
     * reconciliation, as the existing accounting-only tests do. A set of
     * valid ballots must produce a byte-identical matrix and result whether
     * or not it is interleaved with blank/empty/scalar/out-of-roster-only
     * ballots.
     */
    public function test_blank_and_invalid_ballots_leave_the_pairwise_matrix_and_result_byte_identical(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C'], ['seats' => 2]);
        $validRankings = [['A', 'B'], ['A', 'B'], ['B', 'A'], ['C']];

        $clean = $this->votes($c, $validRankings);

        $withNoise = [
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => null]),
            $clean[0],
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => []]]),
            $clean[1],
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => 'A']]),
            $clean[2],
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z']]]),
            $clean[3],
        ];

        $rClean = $this->calc($clean, $c);
        $rNoisy = $this->calc($withNoise, $c);

        $this->assertSame($rClean['pairwise']['matrix'], $rNoisy['pairwise']['matrix']);
        $this->assertSame($rClean['ranking'], $rNoisy['ranking']);
        $this->assertSame($rClean['elected'], $rNoisy['elected']);
        $this->assertSame($rClean['bands'], $rNoisy['bands']);

        // Hand-derived: 8 cast; blank = 3 (unanswered `null`, empty `[]`,
        // and the scalar `'A'` -- accountAndParse treats a non-array or an
        // empty array as blank, never invalid); invalid_only = 1 (`['Z']`,
        // out-of-roster-only); counted = 8 - 3 - 1 = 4, matching the 4 clean
        // ballots -- the noisy run's accounting must show these real
        // figures, not silently match the clean run's (cast=4, blank=0).
        $this->assertSame(8, $rNoisy['accounting']['cast']);
        $this->assertSame(3, $rNoisy['accounting']['blank']);
        $this->assertSame(1, $rNoisy['accounting']['invalid_only']);
        $this->assertSame(4, $rNoisy['accounting']['counted']);
    }
}
