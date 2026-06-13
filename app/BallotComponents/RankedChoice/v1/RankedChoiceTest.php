<?php

declare(strict_types=1);

namespace App\BallotComponents\RankedChoice\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Instant-runoff semantics with deterministic prior-round look-back (D6/D7/D8/D9/D10)
 * on his instance API + DTO ->toArray(). Expected values are our master
 * RankedChoiceTest, adapted to instance calls (no branching/splitElimination).
 */
class RankedChoiceTest extends TestCase
{
    private RankedChoice $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new RankedChoice();
    }

    /**
     * @param array<string> $options
     */
    private function makeComponent(array $options): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => $options,
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param list<list<string>|null> $rankings null => unanswered ballot
     * @return array<int, Vote>
     */
    private function votes(BallotComponent $component, array $rankings): array
    {
        $votes = [];
        foreach ($rankings as $ranking) {
            if ($ranking === null) {
                $votes[] = Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => null]);
                continue;
            }
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

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles', 'David', 'Ernest']);

        $validator = $this->component->getSubmissionValidator($component, $election)->toArray();
        $this->assertEquals([
            $component->id => ['required', 'array'],
            "$component->id.*" => ['distinct', Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])],
        ], $validator);
    }

    public function test_first_round_absolute_majority_wins_immediately_even_n(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['A'], ['A'], ['A'], ['B']]), $c);

        $this->assertCount(1, $r['rounds']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }

    public function test_odd_n_true_majority_recognised_in_round_one(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['A'], ['A'], ['B']]), $c);

        $this->assertCount(1, $r['rounds']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $round = $r['rounds'][0];
        $this->assertSame(0, $round['C']);
        $this->assertSame(2, $round['A']);
        $this->assertSame(1, $round['B']);
        $this->assertSame(3, $round['continuing']);
    }

    public function test_multi_round_transfer_flips_the_leader(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [
            ['A'], ['A'], ['A'], ['A'],
            ['B'], ['B'], ['B'],
            ['C', 'B'], ['C', 'B'],
        ]), $c);

        $this->assertCount(2, $r['rounds']);
        $this->assertSame(4, $r['rounds'][0]['A']);
        $this->assertSame(3, $r['rounds'][0]['B']);
        $this->assertSame(2, $r['rounds'][0]['C']);
        $this->assertSame('C', $r['rounds'][0]['eliminated']);
        $this->assertSame(4, $r['rounds'][1]['A']);
        $this->assertSame(5, $r['rounds'][1]['B']);
        $this->assertSame(9, $r['rounds'][1]['continuing']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('B', $r['result']['conclussive_winner']);
    }

    public function test_zero_votes_returns_empty_shape(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc([], $c);

        $this->assertEquals([], $r['rounds']);
        $this->assertEquals([], $r['result']['winners']);
        // His DTO contract: conclussive is a bool (false), not null.
        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
    }

    public function test_all_abstain_three_options_non_conclusive_no_crash(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [null, null, null]), $c);

        $this->assertFalse($r['result']['conclussive']);
        $this->assertEquals([], $r['result']['winners']);
        $this->assertNull($r['result']['conclussive_winner']);
    }

    public function test_lookback_distinguishes_non_zero_last_place_tie(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D']);
        $r = $this->calc($this->votes($c, [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D', 'B'],
        ]), $c);

        $this->assertCount(3, $r['rounds']);
        $this->assertSame('D', $r['rounds'][0]['eliminated']);
        $this->assertSame(4, $r['rounds'][1]['A']);
        $this->assertSame(3, $r['rounds'][1]['B']);
        $this->assertSame(3, $r['rounds'][1]['C']);
        $this->assertSame('B', $r['rounds'][1]['eliminated']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }

    public function test_genuinely_symmetric_tie_is_non_conclusive_and_reproducible(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'C'], ['B', 'C'],
            ['C', 'B'], ['C', 'B'],
        ];

        $r1 = $this->calc($this->votes($c, $rankings), $c);
        $r2 = $this->calc($this->votes($c, $rankings), $c);

        $this->assertFalse($r1['result']['conclussive']);
        $this->assertNull($r1['result']['conclussive_winner']);
        $this->assertEquals(['B', 'C'], $r1['result']['winners']);
        $this->assertEquals($r1['result'], $r2['result']);
    }

    public function test_existing_fixture_resolves_to_symmetric_non_conclusive_tie(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty', 'Charles', 'David', 'Ernest']);
        $r = $this->calc($this->votes($c, [
            ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
            ['Charles', 'Betty', 'Ernest', 'Ana', 'David'],
            ['Ernest', 'Betty', 'David', 'Charles', 'Ana'],
            ['Ana', 'Betty', 'David', 'Charles', 'Ernest'],
            ['Ernest', 'Betty', 'David', 'Charles', 'Ana'],
            ['Charles', 'Ana', 'David', 'Betty', 'Ernest'],
            ['Betty', 'Ana', 'David', 'Charles', 'Ernest'],
            ['Ana', 'Charles', 'David', 'Ernest', 'Betty'],
        ]), $c);

        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
        $this->assertEquals(['Charles', 'Ernest'], $r['result']['winners']);
        $this->assertNotContains('tie', $r['result']['winners']);
    }

    public function test_all_zero_multi_elimination_drops_options_together(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D']);
        $r = $this->calc($this->votes($c, [['A'], ['A'], ['B'], ['B']]), $c);

        $this->assertSame(0, $r['rounds'][0]['C']);
        $this->assertSame(0, $r['rounds'][0]['D']);
        $this->assertSame('C, D', $r['rounds'][0]['eliminated']);
        $this->assertFalse($r['result']['conclussive']);
        $this->assertEquals(['A', 'B'], $r['result']['winners']);
    }

    public function test_final_two_way_tie_is_non_conclusive_tie_token_not_in_winners(): void
    {
        $c = $this->makeComponent(['A', 'B']);
        $r = $this->calc($this->votes($c, [['A'], ['B']]), $c);

        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
        $this->assertEquals(['A', 'B'], $r['result']['winners']);
        $this->assertNotContains('tie', $r['result']['winners']);
    }

    public function test_exhausted_ballot_leaves_pool_with_per_round_count(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['A'], ['A'], ['B'], ['B'], ['C']]), $c);

        $this->assertSame(0, $r['rounds'][0]['exhausted']);
        $this->assertSame(5, $r['rounds'][0]['continuing']);
        $this->assertSame('C', $r['rounds'][0]['eliminated']);
        $this->assertSame(1, $r['rounds'][1]['exhausted']);
        $this->assertSame(4, $r['rounds'][1]['continuing']);
        $this->assertSame(2, $r['rounds'][1]['A']);
        $this->assertSame(2, $r['rounds'][1]['B']);
        $this->assertFalse($r['result']['conclussive']);
        $this->assertEquals(['A', 'B'], $r['result']['winners']);
    }

    public function test_ranks_not_in_options_are_skipped_as_invalid(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['Z', 'A'], ['A'], ['B']]), $c);

        $this->assertSame(2, $r['rounds'][0]['A']);
        $this->assertSame(1, $r['rounds'][0]['B']);
        $this->assertSame(0, $r['rounds'][0]['C']);
        $this->assertNotContains('Z', array_keys($r['rounds'][0]));
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
    }

    public function test_option_labelled_zero_is_counted(): void
    {
        $c = $this->makeComponent(['0', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['0'], ['0'], ['B']]), $c);

        $this->assertSame(2, $r['rounds'][0]['0']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertEquals('0', $r['result']['conclussive_winner']);
    }

    public function test_single_option_with_votes_crowns_it(): void
    {
        $c = $this->makeComponent(['Only']);
        $r = $this->calc($this->votes($c, [['Only'], ['Only']]), $c);

        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('Only', $r['result']['conclussive_winner']);
    }

    public function test_no_valid_votes_yields_no_winner(): void
    {
        $c = $this->makeComponent(['A', 'B']);
        $r = $this->calc($this->votes($c, [null, null]), $c);

        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
        $this->assertEquals([], $r['result']['winners']);
    }

    public function test_ballot_answering_other_component_id_is_excluded(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $other = $this->makeComponent(['X', 'Y']);

        $votes = $this->votes($c, [['A'], ['A']]);
        $votes[] = Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$other->id => ['X']]]);
        $votes[] = Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$other->id => ['Y']]]);

        $r = $this->calc($votes, $c);

        $this->assertSame(2, $r['rounds'][0]['continuing']);
        $this->assertSame(2, $r['rounds'][0]['A']);
        $this->assertSame(0, $r['rounds'][0]['B']);
        $this->assertSame(0, $r['rounds'][0]['C']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }

    public function test_invalid_rank_never_appears_in_winners(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['Z', 'A'], ['Z', 'A'], ['A'], ['B'], ['C']]), $c);

        $this->assertSame(3, $r['rounds'][0]['A']);
        $this->assertNotContains('Z', array_keys($r['rounds'][0]));
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
        $this->assertNotContains('Z', $r['result']['winners']);
        $this->assertNotSame('Z', $r['result']['conclussive_winner']);
    }

    public function test_non_conclusive_tie_winners_follow_roster_order(): void
    {
        $c = $this->makeComponent(['B', 'A']);
        $r = $this->calc($this->votes($c, [['A'], ['B']]), $c);

        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
        $this->assertSame(['B', 'A'], $r['result']['winners']);
    }

    public function test_conclusive_lookback_eliminates_single_option(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D']);
        $r = $this->calc($this->votes($c, [
            ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D', 'B'],
        ]), $c);

        $this->assertSame('B', $r['rounds'][1]['eliminated']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }

    public function test_multiway_lookback_unresolved_lowest_tie_is_non_conclusive(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D', 'E']);
        $r = $this->calc($this->votes($c, [
            ['A'], ['A'], ['A'], ['A'], ['A'], ['A'],
            ['B'], ['B'], ['B'],
            ['C'], ['C'], ['C'],
            ['D'], ['D'], ['D'], ['D'],
            ['E', 'B'], ['E', 'C'],
        ]), $c);

        $this->assertSame('E', $r['rounds'][0]['eliminated']);
        $this->assertSame(4, $r['rounds'][1]['B']);
        $this->assertSame(4, $r['rounds'][1]['C']);
        $this->assertSame(4, $r['rounds'][1]['D']);
        $this->assertEquals(['B', 'C', 'D'], $r['rounds'][1]['tied']);
        $this->assertFalse($r['result']['conclussive']);
        $this->assertNull($r['result']['conclussive_winner']);
        $this->assertEquals(['B', 'C', 'D'], $r['result']['winners']);
        $this->assertNotContains('tie', $r['result']['winners']);
    }

    public function test_multiway_lookback_recurses_to_earlier_round_to_break_subtie(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D', 'E', 'F']);
        $rankings = [
            ['A'], ['A'], ['A'], ['A'], ['A'], ['A'],
            ['B', 'A'], ['B', 'A'],
            ['C'], ['C'], ['C'],
            ['D'], ['D'], ['D'], ['D'],
            ['E', 'B', 'A'],
            ['E', 'C'],
            ['F', 'B', 'A'],
        ];

        $r = $this->calc($this->votes($c, $rankings), $c);

        $this->assertCount(4, $r['rounds']);
        $this->assertSame('F', $r['rounds'][0]['eliminated']);
        $this->assertSame(3, $r['rounds'][1]['B']);
        $this->assertSame('E', $r['rounds'][1]['eliminated']);
        $this->assertSame(4, $r['rounds'][2]['B']);
        $this->assertSame(4, $r['rounds'][2]['C']);
        $this->assertSame(4, $r['rounds'][2]['D']);
        $this->assertSame('B', $r['rounds'][2]['eliminated']);
        $this->assertSame(10, $r['rounds'][3]['A']);
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }

    public function test_invalid_only_ballot_never_entered_vs_eliminated_only_exhausted(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [
            ['A'], ['A'], ['B'], ['B'], ['C'],
            ['Z'], ['Z'],
        ]), $c);

        $this->assertSame(5, $r['rounds'][0]['continuing']);
        $this->assertSame(0, $r['rounds'][0]['exhausted']);
        $this->assertSame('C', $r['rounds'][0]['eliminated']);
        $this->assertSame(4, $r['rounds'][1]['continuing']);
        $this->assertSame(1, $r['rounds'][1]['exhausted']);
        $this->assertNotContains('Z', array_keys($r['rounds'][0]));
    }

    public function test_calculate_results_shape(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);
        $r = $this->calc($this->votes($c, [['A'], ['A'], ['A'], ['B'], ['C']]), $c);

        $this->assertEquals(['rounds', 'result'], array_keys($r));
        $this->assertEquals(['winners', 'conclussive', 'conclussive_winner'], array_keys($r['result']));
        $this->assertTrue($r['result']['conclussive']);
        $this->assertSame('A', $r['result']['conclussive_winner']);
        $this->assertEquals(['A'], $r['result']['winners']);
    }
}
