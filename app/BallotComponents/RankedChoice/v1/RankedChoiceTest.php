<?php

declare(strict_types=1);

namespace App\BallotComponents\RankedChoice\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class RankedChoiceTest extends TestCase
{
    private RankedChoice $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new RankedChoice();
    }

    /**
     * Build a collection of votes from a list of rankings for a component.
     *
     * @param array<int, array<string>|null> $rankings
     */
    private function votesFor(BallotComponent $component, array $rankings): Collection
    {
        $votes = collect();
        foreach ($rankings as $ranking) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $ranking === null ? null : [$component->id => $ranking],
            ]));
        }
        return $votes;
    }

    private function makeComponent(array $options): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'version' => 'v1',
            'options' => $options,
        ]);
    }

    // ----------------------------------------------------------------
    // Submission validator
    // ----------------------------------------------------------------

    public function test_submission_validator_non_abstainable_requires_array_of_options(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $validator->toArray());
    }

    public function test_submission_validator_abstainable_is_nullable(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['nullable', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $validator->toArray());
    }

    // ----------------------------------------------------------------
    // Result calculation
    // ----------------------------------------------------------------

    public function test_empty_votes_returns_empty_result(): void
    {
        $component = $this->makeComponent(['A', 'B', 'C']);

        $result = $this->component->calculateResults(collect([]), $component)->toArray();

        $this->assertSame([], $result['rounds']);
        $this->assertSame([], $result['result']['winners']);
        $this->assertFalse($result['result']['conclussive']);
        $this->assertNull($result['result']['conclussive_winner']);
    }

    public function test_round_one_majority_is_detected_in_a_single_round(): void
    {
        // 3 of 5 first-choice votes is a true majority and must be recognised
        // immediately (regression guard for the off-by-one threshold fix).
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C'],
            ['A', 'B', 'C'],
            ['A', 'C', 'B'],
            ['B', 'A', 'C'],
            ['B', 'C', 'A'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertCount(1, $result['rounds']);
        $this->assertEquals('A', $result['rounds'][0]['winner']);
        $this->assertEquals(3, $result['rounds'][0]['A']);
        $this->assertEquals(2, $result['rounds'][0]['B']);
        $this->assertEquals(0, $result['rounds'][0]['C']);
        $this->assertEquals(['A'], $result['result']['winners']);
        $this->assertTrue($result['result']['conclussive']);
        $this->assertEquals('A', $result['result']['conclussive_winner']);
    }

    public function test_elimination_redistributes_to_next_preference(): void
    {
        // First choices: A=2, B=2, C=1. No majority -> C eliminated.
        // C's single ballot ([C,B,A]) redistributes to B -> B=3 wins.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C'],
            ['A', 'C', 'B'],
            ['B', 'C', 'A'],
            ['B', 'A', 'C'],
            ['C', 'B', 'A'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertCount(2, $result['rounds']);
        $this->assertEquals('C', $result['rounds'][0]['eliminated']);
        $this->assertEquals([], $result['rounds'][0]['eliminated_previously']);
        $this->assertEquals('B', $result['rounds'][1]['winner']);
        $this->assertEquals(3, $result['rounds'][1]['B']);
        $this->assertEquals(2, $result['rounds'][1]['A']);
        $this->assertEquals(['B'], $result['result']['winners']);
        $this->assertTrue($result['result']['conclussive']);
    }

    public function test_two_option_tie_is_reported(): void
    {
        $component = $this->makeComponent(['A', 'B']);
        $votes = $this->votesFor($component, [
            ['A', 'B'],
            ['A', 'B'],
            ['B', 'A'],
            ['B', 'A'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertCount(1, $result['rounds']);
        $this->assertEquals('tie', $result['rounds'][0]['winner']);
        $this->assertEquals(['tie'], $result['result']['winners']);
    }

    public function test_exhausted_ballot_does_not_count_after_its_options_eliminated(): void
    {
        // The 5th ballot only ranks C. When C is eliminated it is exhausted
        // and contributes to no further round, leaving A=2 / B=2 -> tie.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C'],
            ['A', 'B', 'C'],
            ['B', 'A', 'C'],
            ['B', 'A', 'C'],
            ['C'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals('C', $result['rounds'][0]['eliminated']);
        $finalRound = $result['rounds'][count($result['rounds']) - 1];
        $this->assertEquals(2, $finalRound['A']);
        $this->assertEquals(2, $finalRound['B']);
        $this->assertEquals('tie', $finalRound['winner']);
    }

    public function test_multiple_options_tied_at_zero_are_eliminated_together(): void
    {
        // A=2, B=2, C=0, D=0, no majority. C and D (both zero) are eliminated
        // in a single round.
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C', 'D'],
            ['A', 'B', 'C', 'D'],
            ['B', 'A', 'C', 'D'],
            ['B', 'A', 'C', 'D'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals('C, D', $result['rounds'][0]['eliminated']);
        $finalRound = $result['rounds'][count($result['rounds']) - 1];
        $this->assertEquals('tie', $finalRound['winner']);
    }

    public function test_tie_for_last_with_votes_produces_split_elimination(): void
    {
        // A=2, B=2, C=1, D=1, no majority. C and D are tied for last *with*
        // votes, so the algorithm branches into a split elimination scenario.
        $component = $this->makeComponent(['A', 'B', 'C', 'D']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C', 'D'],
            ['A', 'B', 'C', 'D'],
            ['B', 'A', 'C', 'D'],
            ['B', 'A', 'C', 'D'],
            ['C', 'A', 'B', 'D'],
            ['D', 'A', 'B', 'C'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $lastRound = $result['rounds'][count($result['rounds']) - 1];
        $this->assertArrayHasKey('splitElimination', $lastRound);
        $this->assertArrayHasKey('C', $lastRound['splitElimination']);
        $this->assertArrayHasKey('D', $lastRound['splitElimination']);
        $this->assertNotEmpty($result['result']['winners']);
    }

    public function test_all_votes_abstaining_on_component_does_not_crash(): void
    {
        // Multi-component abstainable ballot: voters answer another component
        // but omit this ranked component entirely. calculateResults must not
        // blow up (max() of an empty option set) when no option has any vote.
        $component = $this->makeComponent(['A', 'B', 'C']);
        $other = 'other-component-id';
        $votes = collect([
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$other => 'yes']]),
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$other => 'no']]),
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$other => 'yes']]),
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        // No effective votes for this component -> no conclusive winner, no crash.
        $this->assertIsArray($result['rounds']);
        $finalRound = $result['rounds'][count($result['rounds']) - 1];
        $this->assertEquals(0, $finalRound['A']);
        $this->assertEquals(0, $finalRound['B']);
        $this->assertEquals(0, $finalRound['C']);
    }

    public function test_get_totals_builds_position_frequency_matrix(): void
    {
        $component = $this->makeComponent(['A', 'B', 'C']);
        $votes = $this->votesFor($component, [
            ['A', 'B', 'C'],
            ['A', 'C', 'B'],
            ['B', 'A', 'C'],
        ]);

        $totals = $this->component->getTotals($votes, $component);

        // A is ranked first twice, second once.
        $this->assertEquals([2, 1, 0], $totals['A']);
        // B: first once, second once, third once.
        $this->assertEquals([1, 1, 1], $totals['B']);
        // C: first zero, second once, third twice.
        $this->assertEquals([0, 1, 2], $totals['C']);
    }

    public function test_values_to_csv_joins_ranking(): void
    {
        $component = $this->makeComponent(['A', 'B', 'C']);

        $csv = $this->component->valuesToCsv([$component->id => ['B', 'A', 'C']], $component->id);
        $this->assertEquals('B, A, C', $csv);

        $this->assertEquals('', $this->component->valuesToCsv([], $component->id));
    }
}
