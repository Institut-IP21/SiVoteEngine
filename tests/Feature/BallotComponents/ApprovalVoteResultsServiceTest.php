<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponents;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ApprovalVote multi-winner (top-K) tally: `settings.seats` (default 1,
 * clamped to [1, optionCount]) selects the K most-approved options. Every
 * option with a count strictly above the cutoff (the count at rank K) is
 * guaranteed a seat (`elected`); options genuinely tied AT the cutoff are
 * only elected outright when they exactly fill the remaining seats —
 * otherwise they're `contested` and the engine never arbitrarily picks among
 * them (same surface-don't-break idiom as OrderedListResult's bands /
 * cutoffDecision). `winner`/`winners` stay exactly the pre-top-K
 * single-highest-count computation, so seats=1 is byte-identical to the
 * original single-winner behavior — the hard back-compat requirement.
 */
class ApprovalVoteResultsServiceTest extends TestCase
{
    use RefreshDatabase;

    private BallotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BallotService::class);
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed>|null $settings
     * @return array{0: Ballot, 1: BallotComponent}
     */
    private function make(array $options, ?array $settings = null, bool $abstainable = false): array
    {
        $election = Election::factory()->create(['abstainable' => $abstainable]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'mode' => Ballot::MODE_BASIC,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'ApprovalVote',
            'title' => 'Committee seats',
            'version' => 'v1',
            'options' => $options,
            'settings' => $settings,
        ]);

        return [$ballot, $component];
    }

    /**
     * @param list<array<string>|null> $approvals One vote per entry; null casts an abstain/blank value.
     */
    private function castVotes(Ballot $ballot, BallotComponent $component, array $approvals): void
    {
        foreach ($approvals as $approved) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $approved])->create();
        }
    }

    /** @return array<string, mixed> */
    private function resultOf(Ballot $ballot, string $componentId): array
    {
        $results = $this->service->calculateResults($ballot);

        return $results[$componentId]['results'];
    }

    public function test_seats_unset_defaults_to_one_and_matches_pre_change_single_winner_result(): void
    {
        [$ballot, $component] = $this->make(['Red', 'Green', 'Blue', 'Yellow']);
        $this->castVotes($ballot, $component, [
            ['Red', 'Green'],
            ['Red', 'Blue'],
            ['Red', 'Green', 'Yellow'],
            ['Blue', 'Yellow'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        // Pre-change formula (the exact code this lane replaced), recomputed
        // independently from the same tally, as the back-compat proof.
        $state = $res['state'];
        $oldWinners = array_map('strval', array_keys($state, max($state), true));
        $oldWinner = count($oldWinners) > 1 ? 'tie' : $oldWinners[0];

        $this->assertSame(1, $res['seats']);
        $this->assertSame($oldWinner, $res['winner']);
        $this->assertSame($oldWinners, $res['winners']);
        $this->assertSame('Red', $res['winner']);
        $this->assertSame(['Red'], $res['winners']);
        $this->assertSame(['Red'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
        $this->assertSame([], $res['warnings']);
    }

    public function test_seats_explicit_one_matches_the_same_result(): void
    {
        [$ballot, $component] = $this->make(['Red', 'Green', 'Blue', 'Yellow'], ['seats' => 1]);
        $this->castVotes($ballot, $component, [
            ['Red', 'Green'],
            ['Red', 'Blue'],
            ['Red', 'Green', 'Yellow'],
            ['Blue', 'Yellow'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(1, $res['seats']);
        $this->assertSame('Red', $res['winner']);
        $this->assertSame(['Red'], $res['winners']);
        $this->assertSame(['Red'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
    }

    public function test_seats_one_tie_matches_pre_change_tie_result_and_elects_no_one_arbitrarily(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C']);
        $this->castVotes($ballot, $component, [
            ['A'],
            ['B'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame('tie', $res['winner']);
        $this->assertSame(['A', 'B'], $res['winners']);
        $this->assertSame([], $res['elected']);
        $this->assertSame(['A', 'B'], $res['contested']);
        $this->assertSame(1, $res['contested_seats']);
    }

    public function test_seats_two_clean_top_k_no_contest(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C', 'D'], ['seats' => 2]);
        // A=4, B=3, C=2, D=1 — no ties anywhere, so the top-2 (A, B) is clean.
        $this->castVotes($ballot, $component, [
            ['A', 'B', 'C', 'D'],
            ['A', 'B', 'C'],
            ['A', 'B'],
            ['A'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(2, $res['seats']);
        $this->assertSame(4, $res['state']['A']);
        $this->assertSame(3, $res['state']['B']);
        $this->assertSame(2, $res['state']['C']);
        $this->assertSame(1, $res['state']['D']);
        $this->assertSame(['A', 'B'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
    }

    public function test_seats_three_clean_top_k_no_contest(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C', 'D', 'E'], ['seats' => 3]);
        // A=4, B=2, C=2, D=1, E=0.
        $this->castVotes($ballot, $component, [
            ['A', 'B'],
            ['A', 'B'],
            ['A', 'C'],
            ['A', 'C', 'D'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(3, $res['seats']);
        $this->assertSame(['A', 'B', 'C'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
    }

    public function test_cutoff_tie_two_options_tied_for_the_last_seat_are_contested_not_elected(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C', 'E'], ['seats' => 2]);
        // A=4 (clear winner of seat 1); B=3 and C=3 genuinely tie for the 1
        // remaining seat (E=2 falls short) — seating both B and C would
        // overflow the single remaining seat, so neither is elected outright.
        $this->castVotes($ballot, $component, [
            ['A', 'B'],
            ['A', 'B'],
            ['A', 'C'],
            ['A', 'C'],
            ['B'],
            ['C'],
            ['E'],
            ['E'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(4, $res['state']['A']);
        $this->assertSame(3, $res['state']['B']);
        $this->assertSame(3, $res['state']['C']);
        $this->assertSame(2, $res['state']['E']);
        $this->assertSame(['A'], $res['elected']);
        $this->assertSame(['B', 'C'], $res['contested']);
        $this->assertSame(1, $res['contested_seats']);
    }

    public function test_three_way_tie_for_two_remaining_seats(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C', 'D'], ['seats' => 2]);
        // A=B=C=1 (all tied at the top), D=0 — none strictly above the cutoff.
        $this->castVotes($ballot, $component, [
            ['A'],
            ['B'],
            ['C'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame([], $res['elected']);
        $this->assertSame(['A', 'B', 'C'], $res['contested']);
        $this->assertSame(2, $res['contested_seats']);
    }

    public function test_seats_at_least_option_count_elects_everyone_no_contest(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C'], ['seats' => 3]);
        $this->castVotes($ballot, $component, [
            ['A', 'B'],
            ['A'],
        ]);
        // A=2, B=1, C=0 — seats == optionCount, so everyone is seated.

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(3, $res['seats']);
        $this->assertSame(['A', 'B', 'C'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
        $this->assertSame([], $res['warnings']);
    }

    public function test_seats_clamp_when_requested_exceeds_roster_emits_warning(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C'], ['seats' => 10]);
        $this->castVotes($ballot, $component, [
            ['A', 'B'],
            ['A'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(3, $res['seats']);
        $this->assertSame(['A', 'B', 'C'], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(['seats clamped to 3 (requested 10, roster has 3)'], $res['warnings']);
    }

    public function test_no_votes_yet_has_no_elected_and_no_winner(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C']);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(0, $res['total_approvals']);
        $this->assertNull($res['winner']);
        $this->assertSame([], $res['winners']);
        $this->assertSame([], $res['elected']);
        $this->assertSame([], $res['contested']);
        $this->assertSame(0, $res['contested_seats']);
    }

    public function test_all_abstain_has_no_elected_and_counts_abstentions(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C'], null, abstainable: true);
        $this->castVotes($ballot, $component, [null, null]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(2, $res['abstentions']);
        $this->assertSame(0, $res['voters']);
        $this->assertSame(0, $res['total_approvals']);
        $this->assertSame([], $res['elected']);
        $this->assertNull($res['winner']);
    }

    public function test_all_blank_when_not_abstainable_has_no_elected_and_counts_invalid(): void
    {
        [$ballot, $component] = $this->make(['A', 'B', 'C'], null, abstainable: false);
        $this->castVotes($ballot, $component, [null, null]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(2, $res['invalid']);
        $this->assertSame(0, $res['voters']);
        $this->assertSame(0, $res['total_approvals']);
        $this->assertSame([], $res['elected']);
        $this->assertNull($res['winner']);
    }

    public function test_option_literally_named_abstain_stays_a_normal_winnable_option(): void
    {
        [$ballot, $component] = $this->make(['Abstain', 'B', 'C']);
        $this->castVotes($ballot, $component, [
            ['Abstain'],
            ['Abstain'],
            ['B'],
        ]);

        $res = $this->resultOf($ballot, $component->id);

        $this->assertSame(2, $res['state']['Abstain']);
        $this->assertSame('Abstain', $res['winner']);
        $this->assertSame(['Abstain'], $res['elected']);
    }

    public function test_participation_counts_unchanged_voters_abstentions_invalid_and_totals(): void
    {
        [$ballot, $component] = $this->make(['A', 'B'], null, abstainable: true);
        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['A']])->create();
        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['A', 'B']])->create();
        Vote::factory()->forBallot($ballot)->withValues([$component->id => null])->create();
        Vote::factory()->forBallot($ballot)->withValues([$component->id => ['Nonexistent']])->create();

        $res = $this->resultOf($ballot, $component->id);

        // 3 ballots have a real (non-null) answer for this component, even
        // though one of those answers is an unknown label — the label being
        // invalid does not make the BALLOT itself an abstention.
        $this->assertSame(3, $res['voters']);
        $this->assertSame(1, $res['abstentions']);
        $this->assertSame(1, $res['invalid']);
        // A: from vote 1 (A) and vote 2 (A,B) = 2; B: from vote 2 = 1.
        $this->assertSame(2, $res['state']['A']);
        $this->assertSame(1, $res['state']['B']);
        $this->assertSame(3, $res['total_approvals']);
        $this->assertSame(4, $res['total_ballots']);
        // D2: rate is approvals ÷ participating voters — unaffected by top-K.
        $this->assertEqualsWithDelta(2 / 3 * 100, $res['state']['A'] / $res['voters'] * 100, 0.0001);
    }
}
