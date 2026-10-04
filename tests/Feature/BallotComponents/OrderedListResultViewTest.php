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
        $res->assertSeeText(trans_choice('components.orderedlist.elected_headline', 2, ['seats' => 2]));
        $res->assertSeeText('A');
        $res->assertSeeText('B');
        $res->assertSeeText('C');
        $res->assertSeeText('D');
        $res->assertSeeText(__('components.orderedlist.cutoff_note'));
        $res->assertDontSeeText(trans_choice('components.orderedlist.contested_headline', 2, ['count' => 2]));
    }

    public function test_genuine_tie_at_cutoff_shows_contested_headline_and_tie_band(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B'], [['A'], ['B']], ['seats' => 1]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(trans_choice('components.orderedlist.contested_headline', 1, ['count' => 1]));
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
        $res->assertSeeText(trans_choice('components.orderedlist.elected_headline', 2, ['seats' => 2]));
        $res->assertDontSeeText(trans_choice('components.orderedlist.contested_headline', 2, ['count' => 2]));
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
        // Both surely-elected seats (A, B) still need their order settled.
        $res->assertSeeText(trans_choice('components.orderedlist.order_ties_note', 2, ['count' => 2]));
        $res->assertDontSeeText(trans_choice('components.orderedlist.contested_headline', 2, ['count' => 2]));
        $res->assertDontSeeText(trans_choice('components.orderedlist.elected_headline', 2, ['seats' => 2]));
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
        $res->assertDontSeeText(trans_choice('components.orderedlist.elected_headline', 2, ['seats' => 2]));
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
     * A binding alternate quota renders the alternation-specific heading and
     * badge (not the generic min/max "with the quota applied"/"promoted by
     * quota" wording), and still carries the official-result badge once
     * feasible and settled.
     */
    public function test_binding_alternate_quota_shows_alternation_heading_and_badge_with_official_badge(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D'],
            array_fill(0, 5, ['A', 'B', 'C', 'D']),
            [
                'seats' => 3,
                'categories' => ['A' => 'M', 'B' => 'M', 'C' => 'M', 'D' => 'F'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.by_votes_alone'));
        $res->assertSeeText(__('components.orderedlist.with_alternation'));
        $res->assertSeeText(__('components.orderedlist.alternated'));
        $res->assertSeeText(__('components.orderedlist.official_badge'));
        $res->assertDontSeeText(__('components.orderedlist.with_quota'));
        $res->assertDontSeeText(__('components.orderedlist.promoted'));
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
        $res->assertDontSeeText(trans_choice('components.orderedlist.elected_headline', 3, ['seats' => 3]));
        $res->assertSeeText(__('components.orderedlist.quota_pending_note'));
        $res->assertDontSee('runner');
        $res->assertDontSee('Runner');
    }

    /**
     * Whether a binding alternation applies at all can hinge on a tie: D
     * (no category) is tied with C for seat 3. If C wins, F,M,F alternation
     * applies; if D wins, the top has an untagged candidate, the quota is
     * void and the votes-alone slate stands. The page must say so and claim
     * neither slate.
     */
    public function test_alternation_that_only_some_tie_resolutions_can_apply_says_so(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D', 'E'],
            [
                ['A', 'B', 'C', 'D', 'E'],
                ['A', 'B', 'D', 'C', 'E'],
            ],
            [
                'seats' => 3,
                'categories' => ['A' => 'F', 'B' => 'F', 'C' => 'M', 'E' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.quota_partly_infeasible'));
        $res->assertDontSeeText(trans_choice('components.orderedlist.elected_headline', 3, ['seats' => 3]));
        // A keeps seat 1 either way; B is surely seated but its seat differs
        // ([A, C, B] by alternation vs [A, B, D] by votes alone).
        $res->assertSeeTextInOrder([__('components.orderedlist.official_badge'), 'A', __('components.orderedlist.seat_undecided'), __('components.orderedlist.seat_undecided')]);
        // The C/D tie is spelled out: each way it can go, and what follows.
        $res->assertSeeTextInOrder([
            trans_choice('components.orderedlist.tie_heading', 2, ['seats' => '2–3']),
            $this->tieIf('C', 'D'),
            '2. C', '3. B',
            __('components.orderedlist.tie_out', ['names' => 'D']),
            $this->tieIf('D', 'C'),
            '2. B', '3. D',
            __('components.orderedlist.tie_out', ['names' => 'C']),
            __('components.orderedlist.tie_option_infeasible'),
        ]);
        $res->assertDontSeeText(__('components.orderedlist.still_tied_for_open_seats', ['names' => 'B, C, D']));
    }

    /**
     * A natural tie straddling the cutoff says how many of the tied get in:
     * A first on every ballot, B/C/D in a perfect cycle, 2 seats.
     */
    public function test_tie_straddling_the_cutoff_says_how_many_of_the_tied_get_in(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D'],
            [
                ['A', 'B', 'C', 'D'],
                ['A', 'C', 'D', 'B'],
                ['A', 'D', 'B', 'C'],
            ],
            ['seats' => 2]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(trans_choice('components.orderedlist.band_seats_left', 1, ['count' => 1]));
    }

    /**
     * Under a binding quota the votes-alone "N of them get in" line would
     * contradict the official slate (min 1 F seats F5, not M3/M4), so it
     * is not shown.
     */
    public function test_band_seats_line_is_hidden_when_a_binding_quota_decides_the_seats(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'M3', 'M4', 'F5'],
            [
                ['A', 'B', 'M3', 'M4', 'F5'],
                ['A', 'B', 'M4', 'M3', 'F5'],
            ],
            [
                'seats' => 3,
                'categories' => ['A' => 'M', 'B' => 'M', 'M3' => 'M', 'M4' => 'M', 'F5' => 'F'],
                'quota' => ['type' => 'min', 'category' => 'F', 'count' => 1, 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(trans_choice('components.orderedlist.elected_headline', 3, ['seats' => 3]));
        $res->assertDontSeeText(trans_choice('components.orderedlist.band_seats_left', 1, ['count' => 1]));
    }

    /**
     * Two ties that do not influence each other read as two short lists
     * (seats 1-2: A or B first; seat 4: D or E), not four combinations.
     */
    public function test_independent_ties_are_explained_separately(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C', 'D', 'E', 'F'],
            [
                ['A', 'B', 'C', 'D', 'E', 'F'],
                ['B', 'A', 'C', 'E', 'D', 'F'],
            ],
            [
                'seats' => 4,
                'categories' => ['A' => 'F', 'B' => 'F', 'C' => 'M', 'D' => 'F', 'E' => 'F', 'F' => 'M'],
                'quota' => ['type' => 'max', 'category' => 'M', 'count' => 2, 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeTextInOrder([
            trans_choice('components.orderedlist.tie_heading', 2, ['seats' => '1–2']),
            $this->tieIf('A', 'B'),
            '1. A', '2. B',
            $this->tieIf('B', 'A'),
            '1. B', '2. A',
            trans_choice('components.orderedlist.tie_heading', 1, ['seats' => '4']),
            $this->tieIf('D', 'E'),
            '4. D',
            __('components.orderedlist.tie_out', ['names' => 'E']),
        ]);
    }

    /**
     * Beyond the corrector's safety cap the page states the ties must be
     * resolved first and still names the possible seat-holders -- never an
     * empty "nobody elected" list.
     */
    public function test_quota_beyond_the_safety_cap_says_resolve_ties_first_and_names_candidates(): void
    {
        config(['ballot.orderedlist_quota_max_nodes' => 1]);
        [, $ballot] = $this->finishedBallot(
            ['F1', 'F2', 'M1', 'M2'],
            [
                ['F1', 'F2', 'M1', 'M2'],
                ['M2', 'M1', 'F2', 'F1'],
            ],
            [
                'seats' => 2,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'M1' => 'M', 'M2' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.orderedlist.quota_too_complex'));
        $res->assertDontSeeText(trans_choice('components.orderedlist.elected_headline', 2, ['seats' => 2]));
        $res->assertSeeText(__('components.orderedlist.still_tied_for_open_seats', ['names' => 'F1, F2, M1, M2']));
    }

    /**
     * Prod regression 2026-10-03: a binding alternation over a natural cut
     * contested only ACROSS groups (M3 vs F2 for seat 4) is final -- the
     * page must show the elected headline and the alternated slate
     * F1, M1, F2, M2, never "1 seat contested" + the natural F1, M1, M2.
     */
    public function test_binding_alternation_over_a_cross_group_cutoff_tie_is_final_and_alternates(): void
    {
        [, $ballot] = $this->finishedBallot(
            ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            [
                ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
                ['F1', 'M1', 'M2', 'F2', 'M3', 'F3'],
            ],
            [
                'seats' => 4,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ]
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(trans_choice('components.orderedlist.elected_headline', 4, ['seats' => 4]));
        $res->assertDontSeeText(trans_choice('components.orderedlist.contested_headline', 1, ['count' => 1]));
        $res->assertDontSeeText(__('components.orderedlist.quota_pending_note'));
        // The official alternated list LEADS (prod 2026-10-04: the votes-alone
        // order on top read as the result); votes alone follows, labelled.
        $res->assertSeeTextInOrder([
            __('components.orderedlist.with_alternation'),
            __('components.orderedlist.official_badge'),
            'F1', 'M1', 'F2', 'M2',
            __('components.orderedlist.cutoff_note'),
            'M3', 'F3',
            __('components.orderedlist.by_votes_alone'),
            __('components.orderedlist.votes_alone_comparison'),
        ]);
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

    private function tieIf(string $ahead, string $behind): string
    {
        return __('components.orderedlist.tie_if', ['clauses' => __('components.orderedlist.tie_clause', ['ahead' => $ahead, 'behind' => $behind])]) . ':';
    }
}
