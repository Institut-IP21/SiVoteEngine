<?php

declare(strict_types=1);

namespace App\BallotComponents\ApprovalVote\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class ApprovalVoteTest extends TestCase
{
    private ApprovalVote $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new ApprovalVote();
    }

    private function makeComponent(array $options): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => $options,
        ]);
    }

    /**
     * @param array<int, array<string>|string|null> $selectionsPerVote
     */
    private function votesFor(BallotComponent $component, array $selectionsPerVote): Collection
    {
        $votes = collect();
        foreach ($selectionsPerVote as $selection) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $selection === null ? null : [$component->id => $selection],
            ]));
        }
        return $votes;
    }

    // ----------------------------------------------------------------
    // Submission validator
    // ----------------------------------------------------------------

    public function test_submission_validator_non_abstainable_requires_array(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $component = $this->makeComponent(['Red', 'Green', 'Blue']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in(['Red', 'Green', 'Blue'])],
        ], $validator->toArray());
    }

    public function test_submission_validator_abstainable_is_nullable(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $component = $this->makeComponent(['Red', 'Green', 'Blue']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['nullable', 'array'],
            "{$component->id}.*" => ['distinct', Rule::in(['Red', 'Green', 'Blue'])],
        ], $validator->toArray());
    }

    // ----------------------------------------------------------------
    // Result calculation
    // ----------------------------------------------------------------

    public function test_counts_each_approval_and_picks_the_winner(): void
    {
        $component = $this->makeComponent(['Red', 'Green', 'Blue', 'Yellow']);
        $votes = $this->votesFor($component, [
            ['Red', 'Green'],
            ['Red', 'Blue'],
            ['Red', 'Green', 'Yellow'],
            ['Blue', 'Yellow'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals(3, $result['state']['Red']);
        $this->assertEquals(2, $result['state']['Green']);
        $this->assertEquals(2, $result['state']['Blue']);
        $this->assertEquals(2, $result['state']['Yellow']);
        // total_votes is the total number of approvals across all ballots.
        $this->assertEquals(9, $result['state']['Red'] + $result['state']['Green'] + $result['state']['Blue'] + $result['state']['Yellow']);
        $this->assertEquals(9, $result['total_votes']);
        $this->assertEquals('Red', $result['winner']);
        $this->assertEquals(['Red'], $result['winners']);
    }

    public function test_detects_a_tie(): void
    {
        $component = $this->makeComponent(['X', 'Y']);
        $votes = $this->votesFor($component, [
            ['X'],
            ['Y'],
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals('tie', $result['winner']);
        $this->assertEqualsCanonicalizing(['X', 'Y'], $result['winners']);
    }

    public function test_empty_votes_returns_empty_result(): void
    {
        $component = $this->makeComponent(['X', 'Y']);

        $result = $this->component->calculateResults(collect([]), $component)->toArray();

        $this->assertEquals([], $result['state']);
        $this->assertEquals(0, $result['total_votes']);
        $this->assertNull($result['winner']);
        $this->assertNull($result['winners']);
    }

    public function test_votes_without_this_component_are_ignored(): void
    {
        // A voter who abstained on this component (key absent) does not count.
        $component = $this->makeComponent(['X', 'Y']);
        $votes = $this->votesFor($component, [
            ['X', 'Y'],
            null,                       // no values at all
        ]);
        $votes->push(Vote::factory()->make([
            'ballot_id' => 'ballot-x',
            'values' => ['some-other-component' => 'foo'],
        ]));

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals(1, $result['state']['X']);
        $this->assertEquals(1, $result['state']['Y']);
        $this->assertEquals(2, $result['total_votes']);
    }

    // ----------------------------------------------------------------
    // CSV
    // ----------------------------------------------------------------

    public function test_values_to_csv_joins_selections(): void
    {
        $component = $this->makeComponent(['X', 'Y', 'Z']);

        $this->assertEquals('X, Z', $this->component->valuesToCsv([$component->id => ['X', 'Z']], $component->id));
        $this->assertEquals('', $this->component->valuesToCsv([], $component->id));
    }
}
