<?php

declare(strict_types=1);

namespace App\BallotComponents\FirstPastThePost\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class FirstPastThePostTest extends TestCase
{
    private FirstPastThePost $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new FirstPastThePost();
    }

    private function makeComponent(array $options): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => $options,
        ]);
    }

    /**
     * @param array<int, string|null> $choices
     */
    private function votesFor(BallotComponent $component, array $choices): Collection
    {
        $votes = collect();
        foreach ($choices as $choice) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $choice === null ? null : [$component->id => $choice],
            ]));
        }
        return $votes;
    }

    // ----------------------------------------------------------------
    // Submission validator
    // ----------------------------------------------------------------

    public function test_submission_validator_non_abstainable(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $validator->toArray());
    }

    public function test_submission_validator_abstainable_adds_abstain_option(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $component = $this->makeComponent(['Ana', 'Betty']);

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', Rule::in(['Ana', 'Betty', 'abstain'])],
        ], $validator->toArray());
    }

    // ----------------------------------------------------------------
    // Result calculation (deterministic)
    // ----------------------------------------------------------------

    public function test_counts_votes_and_picks_plurality_winner(): void
    {
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Ana', 'Betty', 'Betty', 'Charles',
        ]);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals(['Ana' => 3, 'Betty' => 2, 'Charles' => 1], $result['state']);
        $this->assertEquals(6, $result['total_votes']);
        $this->assertEquals('Ana', $result['winner']);
        $this->assertEquals(['Ana'], $result['winners']);
    }

    public function test_detects_a_tie(): void
    {
        $component = $this->makeComponent(['Ana', 'Betty']);
        $votes = $this->votesFor($component, ['Ana', 'Ana', 'Betty', 'Betty']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals('tie', $result['winner']);
        $this->assertEqualsCanonicalizing(['Ana', 'Betty'], $result['winners']);
    }

    public function test_all_options_appear_in_state_including_unvoted(): void
    {
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);
        $votes = $this->votesFor($component, ['Ana', 'Ana']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        // Every defined option must appear for result transparency, including
        // those that received zero votes.
        $this->assertEquals(['Ana' => 2, 'Betty' => 0, 'Charles' => 0], $result['state']);
        $this->assertEquals('Ana', $result['winner']);
        $this->assertEquals(['Ana'], $result['winners']);
    }

    public function test_empty_votes_returns_empty_result(): void
    {
        $component = $this->makeComponent(['Ana', 'Betty']);

        $result = $this->component->calculateResults(collect([]), $component)->toArray();

        $this->assertEquals([], $result['state']);
        $this->assertEquals(0, $result['total_votes']);
        $this->assertNull($result['winner']);
        $this->assertNull($result['winners']);
    }
}
