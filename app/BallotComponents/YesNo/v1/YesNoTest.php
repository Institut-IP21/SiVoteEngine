<?php

declare(strict_types=1);

namespace App\BallotComponents\YesNo\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * YesNo motion semantics (D1/D4/D5/D9/D10) on his instance API + DTO ->toArray().
 */
class YesNoTest extends TestCase
{
    private YesNo $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new YesNo();
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    private function makeComponent(?array $settings = null): BallotComponent
    {
        $component = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
        ]);
        if ($settings !== null) {
            $component->setAttribute('settings', $settings);
        }
        return $component;
    }

    /**
     * @param array<int, string|null> $answers a null entry models a ballot with no answer for this component
     * @return Collection<int, Vote>
     */
    private function votes(BallotComponent $component, array $answers): Collection
    {
        $votes = new Collection();
        foreach ($answers as $answer) {
            $votes->push(Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => $answer === null ? [] : [$component->id => $answer],
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

    public function test_tallies_yes_and_no_and_picks_winner(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, ['yes', 'yes', 'yes', 'no', 'no']));

        $this->assertEquals(['yes' => 3, 'no' => 2], $r['state']);
        $this->assertEquals(5, $r['valid_votes']);
        $this->assertEquals(5, $r['total_votes']);
        $this->assertEquals('yes', $r['winner']);
        $this->assertEquals(['yes'], $r['winners']);
        $this->assertTrue($r['passed']);
    }

    public function test_unvoted_option_still_appears_at_zero(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, ['yes', 'yes']));

        $this->assertEquals(['yes' => 2, 'no' => 0], $r['state']);
        $this->assertEquals('yes', $r['winner']);
    }

    public function test_abstain_is_separate_and_never_winnable(): void
    {
        // D9: a legitimate abstain token (abstainable) is counted apart, never in state.
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, ['yes', 'no', 'abstain', 'abstain']), true);

        $this->assertEquals(['yes' => 1, 'no' => 1], $r['state']);
        $this->assertEquals(2, $r['abstentions']);
        $this->assertEquals(2, $r['valid_votes']);
        // yes == no -> tie among the valid sides; abstain is not a winner.
        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['yes', 'no'], $r['winners']);
    }

    public function test_abstain_is_invalid_when_not_abstainable(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, ['yes', 'abstain']), false);

        $this->assertEquals(['yes' => 1, 'no' => 0], $r['state']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals(1, $r['invalid']);
        $this->assertEquals('yes', $r['winner']);
    }

    public function test_detects_a_tie(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, ['yes', 'yes', 'no', 'no']));

        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['yes', 'no'], $r['winners']);
        $this->assertFalse($r['passed']); // a tie never carries (strict majority floor)
    }

    public function test_empty_votes_returns_empty_result(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, new Collection());

        $this->assertEquals(['yes' => 0, 'no' => 0], $r['state']);
        $this->assertEquals(0, $r['valid_votes']);
        $this->assertNull($r['winner']);
        $this->assertEquals([], $r['winners']);
    }

    public function test_pass_threshold_two_thirds_blocks_simple_majority(): void
    {
        $c = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        // 6 yes / 5 no: simple majority but 54.5% < 2/3.
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 6, 'yes'), array_fill(0, 5, 'no'))));

        $this->assertEquals('yes', $r['winner']);
        $this->assertFalse($r['passed']);
        $this->assertEquals('two_thirds', $r['pass_threshold']);
    }

    public function test_pass_threshold_numeric_default_50_carries(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 6, 'yes'), array_fill(0, 5, 'no'))));

        $this->assertTrue($r['passed']);
        $this->assertEquals(50, $r['pass_threshold']);
    }

    public function test_submission_validator_non_abstainable(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $c = $this->makeComponent();

        $this->assertEquals([
            $c->id => ['required', Rule::in(['yes', 'no'])],
        ], $this->component->getSubmissionValidator($c, $election)->toArray());
    }

    public function test_submission_validator_abstainable_adds_abstain_option(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $c = $this->makeComponent();

        $this->assertEquals([
            $c->id => ['required', Rule::in(['yes', 'no', 'abstain'])],
        ], $this->component->getSubmissionValidator($c, $election)->toArray());
    }

    public function test_pass_threshold_three_quarters_carries_on_exact_boundary(): void
    {
        // three_quarters preset (yes*4 >= 3*valid) had no tally-level coverage.
        // 6 yes / 2 no = exactly 75%: 24 >= 24 -> carries on the boundary.
        $c = $this->makeComponent(['pass_threshold' => 'three_quarters']);
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 6, 'yes'), array_fill(0, 2, 'no'))));

        $this->assertEquals('yes', $r['winner']);
        $this->assertTrue($r['passed']);
        $this->assertEquals('three_quarters', $r['pass_threshold']);
    }

    public function test_pass_threshold_three_quarters_blocks_just_below(): void
    {
        // 5 yes / 2 no ~= 71.4%: 20 < 21 -> blocked despite a clear majority.
        $c = $this->makeComponent(['pass_threshold' => 'three_quarters']);
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 5, 'yes'), array_fill(0, 2, 'no'))));

        $this->assertEquals('yes', $r['winner']);
        $this->assertFalse($r['passed']);
    }

    public function test_pass_threshold_two_thirds_carries_on_exact_boundary(): void
    {
        // 4 yes / 2 no = 66.6..%: 12 >= 12 -> the integer-rational test carries exactly.
        $c = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 4, 'yes'), array_fill(0, 2, 'no'))));

        $this->assertTrue($r['passed']);
    }

    public function test_pass_threshold_numeric_carries_on_exact_boundary(): void
    {
        // 6 yes / 4 no = exactly 60% -> meets the `>=` comparison literally.
        $c = $this->makeComponent(['pass_threshold' => 60]);
        $r = $this->calc($c, $this->votes($c, array_merge(array_fill(0, 6, 'yes'), array_fill(0, 4, 'no'))));

        $this->assertTrue($r['passed']);
        $this->assertEquals(60, $r['pass_threshold']);
    }

    public function test_pass_threshold_zero_valid_votes_never_passes(): void
    {
        // All-abstain under a supermajority threshold: no division by zero, no passage.
        $c = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        $r = $this->calc($c, $this->votes($c, ['abstain', 'abstain']), true);

        $this->assertEquals(['yes' => 0, 'no' => 0], $r['state']);
        $this->assertEquals(0, $r['valid_votes']);
        $this->assertEquals(2, $r['abstentions']);
        $this->assertNull($r['winner']);
        $this->assertFalse($r['passed']);
    }

    public function test_tie_under_supermajority_threshold_never_passes(): void
    {
        // The strict-majority floor (yes > no) short-circuits before the threshold maths.
        $c = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        $r = $this->calc($c, $this->votes($c, ['yes', 'no']));

        $this->assertEquals('tie', $r['winner']);
        $this->assertFalse($r['passed']);
    }
}
