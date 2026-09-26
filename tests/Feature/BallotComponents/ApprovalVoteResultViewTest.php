<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponents;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Locks the ApprovalVote top-K result view: at seats=1 the classic single-
 * winner wording (`winner_is`/`tie`) renders byte-for-byte as before; at
 * seats>1 the new seats-aware headline (`elected_headline`/`contested_headline`,
 * borrowing OrderedList's settled-vs-contested pattern) takes over. The
 * engine never resolves a contested cutoff itself.
 */
class ApprovalVoteResultViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param list<string> $options
     * @param list<array<string>> $approvals
     * @param array<string, mixed> $settings
     * @return array{0: Election, 1: Ballot, 2: BallotComponent}
     */
    private function finishedBallot(array $options, array $approvals, array $settings = [], ?int $quorum = null): array
    {
        $election = Election::factory()->create(['locale' => 'en', 'abstainable' => false]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
            'quorum' => $quorum,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => $options,
            'settings' => $settings === [] ? null : $settings,
        ]);

        foreach ($approvals as $approved) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $approved])->create();
        }

        return [$election, $ballot, $component];
    }

    private function fetchResult(Ballot $ballot): TestResponse
    {
        return $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/result");
    }

    public function test_seats_one_clean_winner_shows_classic_winner_headline(): void
    {
        [, $ballot] = $this->finishedBallot(['Red', 'Green', 'Blue'], [
            ['Red'], ['Red'], ['Green'],
        ]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.approval.winner_is', ['name' => 'Red']));
        $res->assertDontSeeText(__('components.approval.elected_headline', ['seats' => 1]));
    }

    public function test_seats_one_tie_shows_classic_tie_banner(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B'], [['A'], ['B']]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.approval.tie'));
        $res->assertSeeText('A');
        $res->assertSeeText('B');
    }

    public function test_seats_two_clean_top_k_shows_elected_headline(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D'],
            [
                ['A', 'B', 'C', 'D'],
                ['A', 'B', 'C'],
                ['A', 'B'],
                ['A'],
            ],
            ['seats' => 2]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.approval.elected_headline', ['seats' => 2]));
        $res->assertDontSeeText(__('components.approval.contested_headline', ['count' => 1]));
    }

    public function test_cutoff_tie_shows_contested_headline_and_no_arbitrary_pick(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'E'],
            [
                ['A', 'B'], ['A', 'B'], ['A', 'C'], ['A', 'C'],
                ['B'], ['C'], ['E'], ['E'],
            ],
            ['seats' => 2]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.approval.contested_headline', ['count' => 1]));
        $res->assertDontSeeText(__('components.approval.elected_headline', ['seats' => 2]));
    }

    public function test_no_votes_yet_shows_neutral_notice_without_error(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], []);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.approval.no_result_yet'));
    }

    public function test_quorum_not_met_shows_advisory_notice_not_a_binding_headline(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], [['A'], ['A'], ['B']], [], quorum: 100);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.not_binding'));
        $res->assertDontSeeText(__('components.approval.winner_is', ['name' => 'A']));
    }
}
