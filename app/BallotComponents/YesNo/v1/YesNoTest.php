<?php

declare(strict_types=1);

namespace App\BallotComponents\YesNo\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class YesNoTest extends TestCase
{
    private YesNo $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new YesNo();
    }

    private function makeComponent(): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
        ]);
    }

    /**
     * @param array<int, string|null> $answers
     */
    private function votesFor(BallotComponent $component, array $answers): Collection
    {
        $votes = collect();
        foreach ($answers as $answer) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $answer === null ? null : [$component->id => $answer],
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
        $component = $this->makeComponent();

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', Rule::in(['yes', 'no'])],
        ], $validator->toArray());
    }

    public function test_submission_validator_abstainable_adds_abstain_option(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $component = $this->makeComponent();

        $validator = $this->component->getSubmissionValidator($component, $election);

        $this->assertEquals([
            $component->id => ['required', Rule::in(['yes', 'no', 'abstain'])],
        ], $validator->toArray());
    }

    // ----------------------------------------------------------------
    // Result calculation (deterministic)
    // ----------------------------------------------------------------

    public function test_tallies_yes_and_no_and_picks_winner(): void
    {
        $component = $this->makeComponent();
        $votes = $this->votesFor($component, ['yes', 'yes', 'yes', 'no', 'no']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals(['yes' => 3, 'no' => 2], $result['state']);
        $this->assertEquals(5, $result['total_votes']);
        $this->assertEquals('yes', $result['winner']);
        $this->assertEquals(['yes'], $result['winners']);
    }

    public function test_unvoted_option_still_appears_at_zero(): void
    {
        $component = $this->makeComponent();
        $votes = $this->votesFor($component, ['yes', 'yes']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        // "no" got no votes but must still be reported for transparency.
        $this->assertEquals(['yes' => 2, 'no' => 0], $result['state']);
        $this->assertEquals('yes', $result['winner']);
    }

    public function test_counts_abstain_as_its_own_tally(): void
    {
        $component = $this->makeComponent();
        $votes = $this->votesFor($component, ['yes', 'no', 'abstain', 'abstain']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals(2, $result['state']['abstain']);
        $this->assertEquals(1, $result['state']['yes']);
        $this->assertEquals(1, $result['state']['no']);
        // abstain has the plurality here.
        $this->assertEquals('abstain', $result['winner']);
    }

    public function test_detects_a_tie(): void
    {
        $component = $this->makeComponent();
        $votes = $this->votesFor($component, ['yes', 'yes', 'no', 'no']);

        $result = $this->component->calculateResults($votes, $component)->toArray();

        $this->assertEquals('tie', $result['winner']);
        $this->assertEqualsCanonicalizing(['yes', 'no'], $result['winners']);
    }

    public function test_empty_votes_returns_empty_result(): void
    {
        $component = $this->makeComponent();

        $result = $this->component->calculateResults(collect([]), $component)->toArray();

        $this->assertEquals([], $result['state']);
        $this->assertEquals(0, $result['total_votes']);
        $this->assertNull($result['winner']);
        $this->assertNull($result['winners']);
    }
}
