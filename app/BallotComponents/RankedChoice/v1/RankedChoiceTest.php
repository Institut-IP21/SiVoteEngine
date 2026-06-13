<?php

namespace App\BallotComponents\RankedChoice\v1;

use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertNotContains;
use function PHPUnit\Framework\assertCount;

class RankedChoiceTest extends TestCase
{
    /**
     * @param array<string> $options
     */
    private function makeComponent(array $options): BallotComponent
    {
        $ballot = Ballot::factory()->make();
        return BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => $options,
            'ballot' => $ballot,
        ]);
    }

    /**
     * Build a list of Vote models from raw rankings. A `null` ranking is an
     * unanswered ballot (no values at all); an empty array is an answered-but-empty
     * value for this component.
     *
     * @param list<list<string>|null> $rankings
     * @return array<int, Vote>
     */
    private function votes(BallotComponent $component, array $rankings): array
    {
        $votes = [];
        foreach ($rankings as $ranking) {
            if ($ranking === null) {
                $votes[] = Vote::factory()->make(['values' => null]);
                continue;
            }
            $votes[] = Vote::factory()->make([
                'values' => [$component->id => $ranking],
            ]);
        }
        return $votes;
    }

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
        ]);

        $validator = RankedChoice::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => [
                'required',
            ],
            "$component->id.*" => [
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest']),
            ],
        ], $validator);
    }

    // ----- D7: continuing-ballot strict majority -----

    public function test_first_round_absolute_majority_wins_immediately_even_n(): void
    {
        $component = $this->makeComponent(['A', 'B', 'C']);
        // A=3, B=1, C=0; continuing=4; majority needed = intdiv(4,2)+1 = 3.
        $votes = $this->votes($component, [['A'], ['A'], ['A'], ['B']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertCount(1, $r['rounds']); // won in round 1, no spurious rounds
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }

    public function test_odd_n_true_majority_recognised_in_round_one(): void
    {
        // D7: 2 of 3 is a true majority recognised in round 1 (no off-by-one,
        // no spurious extra round). intdiv(3,2)+1 = 2.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [['A'], ['A'], ['B']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertCount(1, $r['rounds']);
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        // Full roster seeded at 0 in order (D10): C present at 0.
        $round = $r['rounds'][0];
        assertSame(0, $round['C']);
        assertSame(2, $round['A']);
        assertSame(1, $round['B']);
        assertSame(3, $round['continuing']);
    }

    public function test_multi_round_transfer_flips_the_leader(): void
    {
        // A leads first preferences but loses after C's transfers (RC-03 spirit).
        // R1: A=4 B=3 C=2 (cont 9, need 5); eliminate C; C->B gives B=5 -> B wins.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['A'], ['A'],
            ['B'], ['B'], ['B'],
            ['C', 'B'], ['C', 'B'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertCount(2, $r['rounds']);
        assertSame(4, $r['rounds'][0]['A']);
        assertSame(3, $r['rounds'][0]['B']);
        assertSame(2, $r['rounds'][0]['C']);
        assertSame('C', $r['rounds'][0]['eliminated']);
        // Round 2 tallies sum to continuing (9), not a never-shrinking denominator.
        assertSame(4, $r['rounds'][1]['A']);
        assertSame(5, $r['rounds'][1]['B']);
        assertSame(9, $r['rounds'][1]['continuing']);
        assertTrue($r['result']['conclussive']);
        assertSame('B', $r['result']['conclussive_winner']);
    }

    // ----- empty / abstain paths -----

    public function test_zero_votes_returns_empty_shape_with_null_conclussive(): void
    {
        $component = $this->makeComponent(['A', 'B', 'C']);

        $r = RankedChoice::calculateResults([], $component);

        assertEquals([], $r['rounds']);
        assertEquals([], $r['result']['winners']);
        assertNull($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
    }

    public function test_all_abstain_three_options_non_conclusive_no_crash(): void
    {
        // D6/D7: all options at 0 -> all-zero, no winnable option, non-conclusive,
        // empty winners, no ValueError crash.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [null, null, null]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertFalse($r['result']['conclussive']);
        assertEquals([], $r['result']['winners']);
        assertNull($r['result']['conclussive_winner']);
    }

    // ----- D6: deterministic look-back tie-break -----

    public function test_lookback_distinguishes_non_zero_last_place_tie(): void
    {
        // R1: A=4 B=2 C=3 D=1 (cont 10, need 6); D unique min eliminated;
        // D's [D,B] transfers to B -> R2: A=4 B=3 C=3; B & C tie for last (non-zero).
        // Prior round 1 distinguishes them (B=2 < C=3) -> eliminate B.
        // B's [B,A] transfers to A -> R3: A=6 C=3 -> A wins conclusively.
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D', 'B'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertCount(3, $r['rounds']);
        assertSame('D', $r['rounds'][0]['eliminated']);
        // Round 2: the deterministic look-back eliminates B (lower in round 1).
        assertSame(4, $r['rounds'][1]['A']);
        assertSame(3, $r['rounds'][1]['B']);
        assertSame(3, $r['rounds'][1]['C']);
        assertSame('B', $r['rounds'][1]['eliminated']);
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }

    public function test_genuinely_symmetric_tie_is_non_conclusive_and_reproducible(): void
    {
        // R1: A=4 B=2 C=2 (cont 8, need 5). B & C tie for last and are identical
        // through all (the only) prior round -> cannot break deterministically ->
        // non-conclusive tie listing exactly B and C. No RNG: identical on re-run.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'C'], ['B', 'C'],
            ['C', 'B'], ['C', 'B'],
        ];

        $r1 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);
        $r2 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);

        assertFalse($r1['result']['conclussive']);
        assertNull($r1['result']['conclussive_winner']);
        assertEquals(['B', 'C'], $r1['result']['winners']);
        // Reproducibility guard.
        assertEquals($r1['result'], $r2['result']);
    }

    public function test_existing_fixture_resolves_to_symmetric_non_conclusive_tie(): void
    {
        // Rewritten from the old splitElimination fixture (asserted ['Ana','tie']).
        // Under deterministic look-back: David (0) batch-eliminated, Betty (1)
        // eliminated, leaving Ana=4 Charles=2 Ernest=2. Charles & Ernest are
        // identical through every prior round -> genuinely symmetric -> non-conclusive
        // tie of exactly {Charles, Ernest}. The 'tie' sentinel must NOT leak.
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles', 'David', 'Ernest']);
        $votes = $this->votes($component, [
            ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
            ['Charles', 'Betty', 'Ernest', 'Ana', 'David'],
            ['Ernest', 'Betty', 'David', 'Charles', 'Ana'],
            ['Ana', 'Betty', 'David', 'Charles', 'Ernest'],
            ['Ernest', 'Betty', 'David', 'Charles', 'Ana'],
            ['Charles', 'Ana', 'David', 'Betty', 'Ernest'],
            ['Betty', 'Ana', 'David', 'Charles', 'Ernest'],
            ['Ana', 'Charles', 'David', 'Ernest', 'Betty'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertFalse($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
        assertEquals(['Charles', 'Ernest'], $r['result']['winners']);
        assertNotContains('tie', $r['result']['winners']);
    }

    public function test_all_zero_multi_elimination_drops_options_together(): void
    {
        // RC-19: options with zero first-preferences are batch-eliminated in one round.
        // R1: A=3 B=2 C=0 D=0 (cont 5, need 3). C & D both at 0 -> batch eliminated.
        // A already has the majority though, so it wins in round 1.
        // Use a fixture where the batch elimination is exercised before a winner:
        // R1: A=2 B=2 C=0 D=0 (cont 4, need 3) -> no majority, batch-elim C,D.
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['B'], ['B'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Round 1 eliminates both zero options together.
        assertSame(0, $r['rounds'][0]['C']);
        assertSame(0, $r['rounds'][0]['D']);
        assertSame('C, D', $r['rounds'][0]['eliminated']);
        // Down to A,B tied at 2 -> non-conclusive two-way tie.
        assertFalse($r['result']['conclussive']);
        assertEquals(['A', 'B'], $r['result']['winners']);
    }

    // ----- #14 guard: final two-way tie -----

    public function test_final_two_way_tie_is_non_conclusive_tie_token_not_in_winners(): void
    {
        // RC-17: options [A,B], one [A] one [B]. Down-to-two 1-1 tie ->
        // conclussive=false, winners are the two real labels, 'tie' NOT present.
        $component = $this->makeComponent(['A', 'B']);
        $votes = $this->votes($component, [['A'], ['B']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertFalse($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
        assertEquals(['A', 'B'], $r['result']['winners']);
        assertNotContains('tie', $r['result']['winners']);
    }

    // ----- D8: exhausted-ballot reporting -----

    public function test_exhausted_ballot_leaves_pool_with_per_round_count(): void
    {
        // RC-29/D8: a lone-ranked ballot [C] exhausts when C is eliminated.
        // R1: A=2 B=2 C=1 (cont 5, exhausted 0, need 3); C unique min eliminated.
        // R2: A=2 B=2 (cont 4, exhausted 1) -> tallies sum to continuing (4),
        // not the original 5. Down-to-two tie.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['B'], ['B'], ['C'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertSame(0, $r['rounds'][0]['exhausted']);
        assertSame(5, $r['rounds'][0]['continuing']);
        assertSame('C', $r['rounds'][0]['eliminated']);
        // Round 2: one ballot exhausted; continuing dropped to 4.
        assertSame(1, $r['rounds'][1]['exhausted']);
        assertSame(4, $r['rounds'][1]['continuing']);
        assertSame(2, $r['rounds'][1]['A']);
        assertSame(2, $r['rounds'][1]['B']);
        assertFalse($r['result']['conclussive']);
        assertEquals(['A', 'B'], $r['result']['winners']);
    }

    // ----- D9: invalid / out-of-options ranks -----

    public function test_ranks_not_in_options_are_skipped_as_invalid(): void
    {
        // D9: a ranked label not in options is skipped (no transfer to it). The
        // ballot ['Z','A'] contributes to A, not to the non-existent Z.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [
            ['Z', 'A'], ['A'], ['B'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Z never appears as a state key; A counted twice (its own + the skipped Z ballot).
        assertSame(2, $r['rounds'][0]['A']);
        assertSame(1, $r['rounds'][0]['B']);
        assertSame(0, $r['rounds'][0]['C']);
        assertNotContains('Z', array_keys($r['rounds'][0]));
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
    }

    // ----- option labelled '0' is counted -----

    public function test_option_labelled_zero_is_counted(): void
    {
        // RC-23: option '0' must keep its first-preference votes (strict null check).
        // '0'=2 B=1 (cont 3, need 2) -> '0' wins.
        $component = $this->makeComponent(['0', 'B', 'C']);
        $votes = $this->votes($component, [['0'], ['0'], ['B']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertSame(2, $r['rounds'][0]['0']);
        assertTrue($r['result']['conclussive']);
        // PHP coerces the numeric-string array key '0' to int 0 in array_keys();
        // the option is correctly counted and wins. Use loose equality (0 == '0').
        assertEquals('0', $r['result']['conclussive_winner']);
    }

    public function test_single_option_with_votes_crowns_it(): void
    {
        $component = $this->makeComponent(['Only']);
        $votes = $this->votes($component, [['Only'], ['Only']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertTrue($r['result']['conclussive']);
        assertSame('Only', $r['result']['conclussive_winner']);
    }

    public function test_no_valid_votes_yields_no_winner(): void
    {
        // A two-option contest where every ballot is unanswered: continuing = 0,
        // top tally = 0 -> no winner (winner null, non-conclusive), no crash.
        $component = $this->makeComponent(['A', 'B']);
        $votes = $this->votes($component, [null, null]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertFalse($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
        assertEquals([], $r['result']['winners']);
    }

    // ----- D7 / RC-11: ballots answering a DIFFERENT component id are excluded -----

    public function test_ballot_answering_other_component_id_is_excluded_from_continuing_and_tally(): void
    {
        // D7 / RC-11: a ballot whose `values` carry only ANOTHER component's id never
        // answered this contest — it must not count toward `continuing` nor any tally.
        // 4 ballots: 2 rank [A] here, 2 answer only a foreign component id.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $other = $this->makeComponent(['X', 'Y']);

        // 2 real answers for this component...
        $votes = $this->votes($component, [['A'], ['A']]);
        // ...plus 2 ballots that answer ONLY the other component's id (not this one).
        $votes[] = Vote::factory()->make(['values' => [$other->id => ['X']]]);
        $votes[] = Vote::factory()->make(['values' => [$other->id => ['Y']]]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Continuing counts only the 2 answering ballots, not 4.
        assertSame(2, $r['rounds'][0]['continuing']);
        // Tallies sum to continuing (=2): A=2, B=0, C=0.
        assertSame(2, $r['rounds'][0]['A']);
        assertSame(0, $r['rounds'][0]['B']);
        assertSame(0, $r['rounds'][0]['C']);
        // A is the majority among the answering ballots and wins conclusively.
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }

    // ----- D9 guard: an invalid/out-of-options rank can never win -----

    public function test_invalid_rank_never_appears_in_winners_or_conclussive_winner(): void
    {
        // D9: a label not in options ('Z') is skipped at tally. Even when a ballot
        // leads with 'Z', it transfers past to the next valid preference. 'Z' must
        // never surface in winners or conclussive_winner and is never a state key.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [
            ['Z', 'A'], ['Z', 'A'], ['A'], ['B'], ['C'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // A=3 (own + two Z-led ballots transferred), B=1, C=1; majority of 5 is 3.
        assertSame(3, $r['rounds'][0]['A']);
        assertNotContains('Z', array_keys($r['rounds'][0]));
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
        // 'Z' never appears anywhere a winner could.
        assertNotContains('Z', $r['result']['winners']);
        assertNotSame('Z', $r['result']['conclussive_winner']);
    }

    // ----- D10: winners order follows component->options on a non-conclusive tie -----

    public function test_non_conclusive_tie_winners_follow_roster_order(): void
    {
        // D10: a symmetric two-way tie surfaces both labels in ROSTER order, not in
        // ballot-discovery order. Roster declares [B, A]; ballots discover A first.
        // Down-to-two 1-1 tie -> non-conclusive; winners must be ['B', 'A'] (roster),
        // not ['A', 'B'] (the order they were first seen on ballots).
        $component = $this->makeComponent(['B', 'A']);
        $votes = $this->votes($component, [['A'], ['B']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertFalse($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
        // Roster order is [B, A]; assert winners follow it exactly.
        assertSame(['B', 'A'], $r['result']['winners']);
    }

    // ----- D6.2: conclusive look-back eliminates a single option, conclussive=true -----

    public function test_conclusive_lookback_eliminates_single_option(): void
    {
        // D6.2: a non-zero last-place tie that the most-recent prior round distinguishes
        // cleanly (unique lowest there) yields a SINGLE eliminated option and a single
        // conclusive winner.
        // R1: A=4 B=2 C=3 D=1 (cont 10, need 6); D unique min eliminated.
        // D's [D,B] transfers to B -> R2: A=4 B=3 C=3; B & C tie for last (non-zero).
        // Most-recent prior (round 1) distinguishes: B=2 < C=3 -> eliminate B (unique).
        // B's [B,A] transfers to A -> R3: A=6 C=3 -> A wins conclusively.
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D', 'B'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Round 2 is the look-back round: B is the single eliminated option.
        assertSame('B', $r['rounds'][1]['eliminated']);
        // A single conclusive winner emerges.
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }

    // ----- Reproducibility: deterministic, no RNG -----

    public function test_lookback_resolved_conclusive_case_is_reproducible(): void
    {
        // The look-back path must be deterministic: calling calculateResults twice on
        // the same ballots yields an identical result (no RNG / lot).
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D', 'B'],
        ];

        $r1 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);
        $r2 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);

        // The look-back resolves to a single conclusive winner...
        assertTrue($r1['result']['conclussive']);
        assertSame('A', $r1['result']['conclussive_winner']);
        // ...identically on a second independent run (deterministic).
        assertEquals($r1['result'], $r2['result']);
        assertEquals($r1['rounds'], $r2['rounds']);
    }

    // ----- D6.3: multi-way (3+) look-back that CANNOT resolve -> non-conclusive -----

    public function test_multiway_lookback_unresolved_lowest_tie_is_non_conclusive(): void
    {
        // D6.3 boundary: a 3-way last-place tie whose most-recent DISTINGUISHING prior
        // round still ties the lowest among the tied options, so breakTieByLookback()
        // returns null and the contest is reported NON-CONCLUSIVE.
        // R1: A=6 B=3 C=3 D=4 E=2 (cont 18, need 10); E unique min eliminated.
        // E's [E,B] and [E,C] transfer -> R2: A=6 B=4 C=4 D=4; B,C,D tie 3-way for last.
        // Look-back to round 1 among {B,C,D}: 3,3,4 -> the lowest (3) is itself tied
        // between B and C. Under the backward-RECURSION refinement we narrow to {B,C}
        // and look at even-earlier rounds -- but round 1 is the ONLY prior round, so
        // there is no earlier round to separate B from C: they are symmetric through
        // ALL prior rounds -> breakTieByLookback() returns null -> NON-CONCLUSIVE.
        //
        // This is the genuine-symmetry floor: recursion still bottoms out at null here
        // because no earlier round exists. The test pins that the refinement does NOT
        // wrongly resolve a truly symmetric tie (all three tied labels, roster order).
        $component = $this->makeComponent(['A', 'B', 'C', 'D', 'E']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['A'], ['A'], ['A'], ['A'],
            ['B'], ['B'], ['B'],
            ['C'], ['C'], ['C'],
            ['D'], ['D'], ['D'], ['D'],
            ['E', 'B'], ['E', 'C'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Round 1 eliminates E (unique min); the look-back round is round 1.
        assertSame('E', $r['rounds'][0]['eliminated']);
        // Round 2: the 3-way tie B=C=D=4 that look-back cannot break.
        assertSame(4, $r['rounds'][1]['B']);
        assertSame(4, $r['rounds'][1]['C']);
        assertSame(4, $r['rounds'][1]['D']);
        assertEquals(['B', 'C', 'D'], $r['rounds'][1]['tied']);
        // CURRENT behaviour: non-conclusive, winners are exactly the three tied labels.
        assertFalse($r['result']['conclussive']);
        assertNull($r['result']['conclussive_winner']);
        assertEquals(['B', 'C', 'D'], $r['result']['winners']);
        assertNotContains('tie', $r['result']['winners']);
    }

    // ----- D6.3 refinement: recurse to an EARLIER round to break a sub-tie -----

    public function test_multiway_lookback_recurses_to_earlier_round_to_break_subtie(): void
    {
        // D6.3 backward-recursion refinement. A 3-way last-place tie at R3 whose
        // most-recent distinguishing prior round (R2) narrows the elimination
        // candidates to a 2-WAY sub-tie; an EVEN-EARLIER round (R1) then resolves that
        // sub-tie uniquely -> a single deterministic elimination -> a CONCLUSIVE winner.
        //
        // Fixture (18 ballots, options [A,B,C,D,E,F]):
        //   6x [A]      2x [B,A]   3x [C]   4x [D]
        //   1x [E,B,A]  1x [E,C]   1x [F,B,A]
        //
        // R1 (omit {}):        A=6 B=2 C=3 D=4 E=2 F=1 (cont 18, need 10)
        //                      -> F=1 is the unique min -> eliminate F.
        // R2 (omit {F}):       A=6 B=3 C=3 D=4 E=2     ([F,B,A] -> B)
        //                      -> E=2 is the unique min -> eliminate E.
        // R3 (omit {F,E}):     A=6 B=4 C=4 D=4         ([E,B,A] -> B, [E,C] -> C)
        //                      -> B,C,D tie 3-way for last (non-zero).
        //     Look-back among {B,C,D}:
        //       most-recent prior R2: B=3 C=3 D=4 -> min 3 -> {B,C} (2-way sub-tie);
        //       recurse to EARLIER round R1 among {B,C}: B=2 C=3 -> min 2 -> B unique.
        //       => eliminate B (resolved by recursion, NOT non-conclusive).
        // R4 (omit {F,E,B}):   A=10 C=4 D=4            ([B,A],[F,B,A],[E,B,A] -> A)
        //                      -> A holds the majority (10 >= 10) -> A wins conclusively.
        $component = $this->makeComponent(['A', 'B', 'C', 'D', 'E', 'F']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D'], ['D'], ['D'], ['D'],
            ['E', 'B', 'A'],
            ['E', 'C'],
            ['F', 'B', 'A'],
        ];

        $r = RankedChoice::calculateResults($this->votes($component, $rankings), $component);

        assertCount(4, $r['rounds']);
        // R1: F is the unique min, eliminated.
        assertSame('F', $r['rounds'][0]['eliminated']);
        // R2: E is the unique min, eliminated; B has risen to 3 via F's transfer.
        assertSame(3, $r['rounds'][1]['B']);
        assertSame('E', $r['rounds'][1]['eliminated']);
        // R3: the 3-way tie B=C=D=4 that the backward RECURSION breaks by eliminating B
        // (R2 narrows to {B,C}; R1 singles out B). The single deterministic elimination.
        assertSame(4, $r['rounds'][2]['B']);
        assertSame(4, $r['rounds'][2]['C']);
        assertSame(4, $r['rounds'][2]['D']);
        assertSame('B', $r['rounds'][2]['eliminated']);
        // R4: A reaches the majority and wins CONCLUSIVELY.
        assertSame(10, $r['rounds'][3]['A']);
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }

    public function test_lookback_recursion_resolved_case_is_reproducible(): void
    {
        // Reproducibility guard for the backward-recursion path: identical ballots yield
        // an identical result (and identical rounds) on a second independent run. No RNG.
        $component = $this->makeComponent(['A', 'B', 'C', 'D', 'E', 'F']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D'], ['D'], ['D'], ['D'],
            ['E', 'B', 'A'],
            ['E', 'C'],
            ['F', 'B', 'A'],
        ];

        $r1 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);
        $r2 = RankedChoice::calculateResults($this->votes($component, $rankings), $component);

        assertTrue($r1['result']['conclussive']);
        assertSame('A', $r1['result']['conclussive_winner']);
        assertEquals($r1['result'], $r2['result']);
        assertEquals($r1['rounds'], $r2['rounds']);
    }

    // ----- D8/D9: invalid-only (never entered) vs eliminated-only (exhausted) -----

    public function test_invalid_only_ballot_never_entered_vs_eliminated_only_exhausted(): void
    {
        // D8/D9 boundary. Two ballots ranking ONLY an out-of-options label (['Z'], Z is
        // not an option) must be treated as NEVER-ENTERED: they count toward neither
        // `continuing` nor `exhausted` in any round. By contrast, a ballot whose only
        // valid ranked option (['C']) gets eliminated IS counted as exhausted once C goes.
        // R1: A=2 B=2 C=1 (cont 5, exhausted 0); the two ['Z'] ballots are absent from
        //     both figures -> continuing is 5, not 7. C is the unique min, eliminated.
        // R2: A=2 B=2 (cont 4, exhausted 1) -> the ['C'] ballot is now exhausted; the
        //     two ['Z'] ballots are STILL never-entered (exhausted stays 1, not 3).
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [
            ['A'], ['A'], ['B'], ['B'], ['C'],
            ['Z'], ['Z'],
        ]);

        $r = RankedChoice::calculateResults($votes, $component);

        // Round 1: invalid-only ballots excluded from continuing AND not yet exhausted.
        assertSame(5, $r['rounds'][0]['continuing']);
        assertSame(0, $r['rounds'][0]['exhausted']);
        assertSame('C', $r['rounds'][0]['eliminated']);
        // Round 2: the eliminated-only ballot ['C'] is now exhausted (1); the invalid-only
        // ['Z'] ballots are NEVER counted as exhausted (would be 3 if miscounted).
        assertSame(4, $r['rounds'][1]['continuing']);
        assertSame(1, $r['rounds'][1]['exhausted']);
        // Z never becomes a tally key.
        assertNotContains('Z', array_keys($r['rounds'][0]));
    }

    public function test_calculate_results(): void
    {
        // Backwards-named keeper: a clean majority winner with the full contract shape.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votes($component, [['A'], ['A'], ['A'], ['B'], ['C']]);

        $r = RankedChoice::calculateResults($votes, $component);

        assertEquals(['rounds', 'result'], array_keys($r));
        assertEquals(['winners', 'conclussive', 'conclussive_winner'], array_keys($r['result']));
        assertTrue($r['result']['conclussive']);
        assertSame('A', $r['result']['conclussive_winner']);
        assertEquals(['A'], $r['result']['winners']);
    }
}
