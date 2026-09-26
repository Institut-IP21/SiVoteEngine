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
 * Locks the OrderedList result-first display: elected/contested headline,
 * the full ordered list (with the seat-cutoff divider), the optional quota
 * comparison, and the collapsed "How the order was decided" disclosure
 * (pairwise matrix + Schulze beatpath auditor + accounting) — plus the
 * quorum-not-met advisory branch. Any genuine tie the votes leave (at the
 * cutoff, in the order among elected candidates, or inside a quota
 * correction) is SURFACED: there is no runner and no resolution mechanism,
 * so it stays that way permanently, never auto-applied.
 */
class OrderedListResultViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param list<string> $options
     * @param list<list<string>> $rankings
     * @param array<string, mixed> $settings
     * @return array{0: Election, 1: Ballot, 2: BallotComponent}
     */
    private function finishedBallot(array $options, array $rankings, array $settings = [], ?int $quorum = null): array
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
        $res->assertDontSee('runner');
        $res->assertDontSee('Runner');
    }

    /**
     * F3: a decided top-K with an unresolved band entirely BELOW the cutoff
     * (never-approved C/D/E tied for the tail, seats=2) must still render the
     * success/elected headline -- membership of the top 2 is fully settled,
     * so this is not "seats contested".
     */
    public function test_decided_top_k_with_a_below_cutoff_band_shows_elected_not_contested(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D', 'E'],
            [
                // A decisively over B, both decisively over the untouched
                // C/D/E tail: A and B land as determined singletons at
                // positions 1 and 2.
                ...array_fill(0, 3, ['A', 'B']),
                // One solo-approval ballot per tail candidate makes every
                // C/D/E pairwise comparison tie (each beats the two
                // unapproved others equally often across these three
                // ballots), so C, D, E band together for positions 3-5,
                // entirely below the seats=2 cutoff.
                ['C'],
                ['D'],
                ['E'],
            ],
            ['seats' => 2]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.elected_headline', ['seats' => 2]));
        $res->assertDontSeeText(__('components.orderedlist.contested_headline', ['count' => 2]));
        // The C/D/E tail is still shown as a tie, just not as a headline contest.
        $res->assertSeeText(__('components.orderedlist.tie_awaiting'));
    }

    /**
     * F3/F6: an order-tie strictly among already-elected candidates (A~B for
     * positions 1-2, seats=2; C~D tied below the cutoff, excluded either
     * way) must be reported as an ORDER tie, never as "seats contested" --
     * membership of the top 2 (A and B) is not in doubt, only their relative
     * order is.
     */
    public function test_order_tie_among_elected_shows_order_ties_note_not_contested(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D'],
            [
                // A and B are approved on every one of these 6 ballots (3
                // each way), so both decisively beat C/D, but tie 3-3 with
                // each other: {A, B} band for positions 1-2.
                ...array_fill(0, 3, ['A', 'B']),
                ...array_fill(0, 3, ['B', 'A']),
                // A single 1-1 split ties C and D for positions 3-4,
                // entirely below the cutoff -- excluded either way.
                ['C', 'D'],
                ['D', 'C'],
            ],
            ['seats' => 2]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.order_ties_note', ['count' => 1]));
        $res->assertDontSeeText(__('components.orderedlist.contested_headline', ['count' => 2]));
        $res->assertDontSeeText(__('components.orderedlist.elected_headline', ['seats' => 2]));
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

    /**
     * A binding quota whose only eligible promotion candidate (D) is
     * genuinely tied with another below-cutoff candidate (E) must stay
     * PROVISIONAL forever -- there is no runner, and no resolution
     * mechanism, that could ever pick one over the other. The headline
     * must not claim finality while that quota correction is pending.
     * The natural top-3 (A, B, C) itself carries no order tie, isolating
     * this as purely a quota/surfaced-tie interaction.
     */
    public function test_quota_touching_a_surfaced_tie_hides_elected_headline_and_shows_quota_pending_note(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D', 'E'],
            [
                ...array_fill(0, 3, ['A', 'B', 'C']),
                ['D'],
                ['E'],
            ],
            [
                'seats' => 3,
                'categories' => ['D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertDontSeeText(__('components.orderedlist.elected_headline', ['seats' => 3]));
        $res->assertSeeText(__('components.orderedlist.quota_pending_note'));
        $res->assertDontSee('runner');
        $res->assertDontSee('Runner');
    }

    public function test_disclosure_is_collapsed_and_shows_pairwise_matrix_beatpath_and_accounting(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], [['A', 'B'], ['A', 'B'], ['A']], ['seats' => 2]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.how_decided'));
        $res->assertSee('x-show="open"', false);
        $res->assertSee('style="display: none;"', false);
        $res->assertSeeText(__('components.orderedlist.pairwise_heading'));
        $res->assertSeeText(__('components.orderedlist.beatpath_heading'));
        $res->assertSeeText(__('components.orderedlist.beatpath_why_heading'));
        $res->assertSeeText(__('components.orderedlist.accounting'));
        $res->assertSee('overflow-x-auto', false);
    }

    /**
     * No trace of the old human-"runner" resolution affordance may remain
     * anywhere on the page, across every rendered branch: contested cutoff,
     * order-tie note, and a surfaced quota correction.
     */
    public function test_no_runner_affordance_appears_anywhere_on_the_page(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D', 'E'],
            [
                ...array_fill(0, 3, ['A', 'B', 'C']),
                ['D'],
                ['E'],
            ],
            [
                'seats' => 3,
                'categories' => ['D' => 'Sales', 'E' => 'Sales'],
                'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertDontSee('runner');
        $res->assertDontSee('Runner');
        $res->assertDontSee('resolve-tie');
        $res->assertDontSee('Ranked Pairs');
    }
}
