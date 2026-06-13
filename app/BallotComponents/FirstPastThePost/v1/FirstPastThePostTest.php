<?php

declare(strict_types=1);

namespace App\BallotComponents\FirstPastThePost\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * First-past-the-post semantics (D1/D9/D10) on his instance API + DTO ->toArray().
 */
class FirstPastThePostTest extends TestCase
{
    private FirstPastThePost $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new FirstPastThePost();
    }

    /**
     * @param array<int, string> $options
     */
    private function makeComponent(array $options): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => $options,
        ]);
    }

    /**
     * @param array<int, string|null> $choices a null entry models a ballot with no answer for this component
     * @return Collection<int, Vote>
     */
    private function votes(BallotComponent $component, array $choices): Collection
    {
        $votes = new Collection();
        foreach ($choices as $choice) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $choice === null ? [] : [$component->id => $choice],
            ]));
        }
        return $votes;
    }

    /**
     * @param Collection<int, Vote> $votes
     * @return array<string, mixed>
     */
    private function calc(BallotComponent $c, Collection $votes, bool $abstainable = false): array
    {
        return $this->component->calculateResults($votes, $c, $abstainable)->toArray();
    }

    public function test_counts_votes_and_picks_plurality_winner(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty', 'Charles']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'Ana', 'Ana', 'Betty', 'Betty', 'Charles']));

        $this->assertEquals(['Ana' => 3, 'Betty' => 2, 'Charles' => 1], $r['state']);
        $this->assertEquals(6, $r['valid_votes']);
        $this->assertEquals(6, $r['total_votes']);
        $this->assertEquals('Ana', $r['winner']);
        $this->assertEquals(['Ana'], $r['winners']);
    }

    public function test_detects_a_tie(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'Ana', 'Betty', 'Betty']));

        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['Ana', 'Betty'], $r['winners']);
    }

    public function test_all_options_appear_in_state_including_unvoted(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty', 'Charles']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'Ana']));

        $this->assertEquals(['Ana' => 2, 'Betty' => 0, 'Charles' => 0], $r['state']);
        $this->assertEquals('Ana', $r['winner']);
        $this->assertEquals(['Ana'], $r['winners']);
    }

    public function test_abstain_separated_when_abstainable(): void
    {
        // D9: abstain token + missing answer are abstentions, never winnable.
        $c = $this->makeComponent(['Ana', 'Betty']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'abstain', null]), true);

        $this->assertEquals(['Ana' => 1, 'Betty' => 0], $r['state']);
        $this->assertEquals(1, $r['valid_votes']);
        $this->assertEquals(2, $r['abstentions']);
        $this->assertEquals(0, $r['invalid']);
        $this->assertEquals(3, $r['total_votes']);
        $this->assertEquals('Ana', $r['winner']);
    }

    public function test_abstain_and_missing_are_invalid_when_not_abstainable(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'abstain', null]), false);

        $this->assertEquals(['Ana' => 1, 'Betty' => 0], $r['state']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals(2, $r['invalid']);
        $this->assertEquals('Ana', $r['winner']);
    }

    public function test_unknown_label_is_invalid(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty']);
        $r = $this->calc($c, $this->votes($c, ['Ana', 'Zoe']));

        $this->assertEquals(['Ana' => 1, 'Betty' => 0], $r['state']);
        $this->assertEquals(1, $r['invalid']);
        $this->assertEquals('Ana', $r['winner']);
        $this->assertNotContains('Zoe', $r['winners']);
    }

    public function test_empty_votes_returns_empty_result(): void
    {
        $c = $this->makeComponent(['Ana', 'Betty']);
        $r = $this->calc($c, new Collection());

        $this->assertEquals(['Ana' => 0, 'Betty' => 0], $r['state']);
        $this->assertEquals(0, $r['valid_votes']);
        $this->assertNull($r['winner']);
        $this->assertEquals([], $r['winners']);
    }

    public function test_submission_validator_non_abstainable(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $c = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $this->assertEquals([
            $c->id => ['required', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $this->component->getSubmissionValidator($c, $election)->toArray());
    }

    public function test_submission_validator_abstainable_adds_abstain_option(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $c = $this->makeComponent(['Ana', 'Betty']);

        $this->assertEquals([
            $c->id => ['required', Rule::in(['Ana', 'Betty', 'abstain'])],
        ], $this->component->getSubmissionValidator($c, $election)->toArray());
    }
}
