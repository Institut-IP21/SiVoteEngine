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
 * Locks the OrderedList result-first display (Task 11): elected/contested
 * headline, the full ordered list (with the seat-cutoff divider), the
 * optional quota comparison, and the collapsed "How the order was decided"
 * disclosure (pairwise matrix + lock-in log + accounting) — plus the
 * quorum-not-met advisory branch.
 */
class OrderedListResultViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param list<string> $options
     * @param list<list<string>> $rankings
     * @param array<string, mixed> $settings
     * @param list<array<string, mixed>> $resolutions
     * @return array{0: Election, 1: Ballot, 2: BallotComponent}
     */
    private function finishedBallot(array $options, array $rankings, array $settings = [], ?int $quorum = null, array $resolutions = []): array
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
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => $options,
            'settings' => $settings === [] ? null : $settings,
            'runner_resolutions' => $resolutions === [] ? null : $resolutions,
        ]);

        foreach ($rankings as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        return [$election, $ballot, $component];
    }

    private function fetchResult(Ballot $ballot): TestResponse
    {
        return $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/result");
    }

    public function test_clean_election_shows_elected_headline_and_full_list(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C', 'D'], array_fill(0, 5, ['A', 'B', 'C', 'D']), ['seats' => 2]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.elected_headline', ['seats' => 2]));
        $res->assertSeeText('A');
        $res->assertSeeText('B');
        $res->assertSeeText('C');
        $res->assertSeeText('D');
        $res->assertSeeText(__('components.orderedlist.cutoff_note'));
        $res->assertDontSeeText(__('components.orderedlist.contested_headline', ['count' => 2]));
    }

    public function test_genuine_tie_at_cutoff_shows_contested_headline_and_tie_band(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B'], [['A'], ['B']], ['seats' => 1]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.contested_headline', ['count' => 1]));
        $res->assertSeeText(__('components.orderedlist.tie_awaiting'));
    }

    public function test_no_votes_yet_shows_neutral_notice_without_error(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], []);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.no_result_yet'));
    }

    public function test_quorum_not_met_shows_advisory_notice_not_a_binding_headline(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C', 'D'], array_fill(0, 5, ['A', 'B', 'C', 'D']), ['seats' => 2], quorum: 100);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.outcome_not_binding'));
        $res->assertDontSeeText(__('components.orderedlist.elected_headline', ['seats' => 2]));
        // The list still renders as advisory evidence under the notice.
        $res->assertSeeText('A');
    }

    public function test_binding_quota_shows_comparison_with_promoted_pill_and_official_badge(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D'],
            array_fill(0, 5, ['A', 'B', 'C', 'D']),
            [
                'seats' => 2,
                'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Eng'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.by_votes_alone'));
        $res->assertSeeText(__('components.orderedlist.with_quota'));
        $res->assertSeeText(__('components.orderedlist.promoted'));
        $res->assertSeeText(__('components.orderedlist.official_badge'));
    }

    public function test_runner_resolution_shows_announced_order_and_elected_headline(): void
    {
        [, $ballot, $component] = $this->finishedBallot(
            ['A', 'B'],
            [['A'], ['B']],
            ['seats' => 1],
            resolutions: [[
                'cluster' => ['A', 'B'],
                'order' => ['A', 'B'],
                'comment' => 'Coin toss witnessed by both agents.',
                'resolved_by' => 'returning-officer',
                'resolved_at' => '2026-09-26T10:00:00+00:00',
            ]]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.runner_announced', ['comment' => 'Coin toss witnessed by both agents.']));
        $res->assertSeeText(__('components.orderedlist.elected_headline', ['seats' => 1]));
    }

    public function test_disclosure_is_collapsed_and_shows_pairwise_matrix_and_accounting(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], [['A', 'B'], ['A', 'B'], ['A']], ['seats' => 2]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.how_decided'));
        $res->assertSee('x-show="open"', false);
        $res->assertSee('style="display: none;"', false);
        $res->assertSeeText(__('components.orderedlist.pairwise_heading'));
        $res->assertSeeText(__('components.orderedlist.accounting'));
        $res->assertSee('overflow-x-auto', false);
    }
}
