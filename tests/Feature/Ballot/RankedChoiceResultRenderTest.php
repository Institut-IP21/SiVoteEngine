<?php

declare(strict_types=1);

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the result-first ranked-choice results display: the public sees a plain
 * outcome sentence + the final standing as bars, while the auditor-grade
 * round-by-round runoff is tucked behind a "How the count worked" toggle that is
 * collapsed by default in every case (conclusive, tie, quorum-not-met).
 */
class RankedChoiceResultRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param list<string> $options
     * @param list<list<string>> $rankings each voter's ordered preference list
     */
    private function finishedBallot(array $options, array $rankings, ?int $quorum = null): array
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
            'type' => 'RankedChoice',
            'version' => 'v1',
            'options' => $options,
        ]);

        foreach ($rankings as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        return [$election, $ballot, $component];
    }

    private function fetchResult(Ballot $ballot): \Illuminate\Testing\TestResponse
    {
        return $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/result");
    }

    public function test_first_round_majority_shows_winner_and_majority_sentence(): void
    {
        // Three ballots all rank A first -> A wins outright in round one.
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], [['A'], ['A'], ['A', 'B']]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.rankedchoice.winner_headline', ['name' => 'A']));
        $res->assertSeeText(__('components.rankedchoice.outcome_majority', ['pct' => 100]));
        // Final standing section is present.
        $res->assertSeeText(__('components.rankedchoice.final_standing'));
    }

    public function test_multi_round_winner_shows_after_rounds_sentence(): void
    {
        // Round 1: A=2, B=2, C=1 -> eliminate C; its [C,A] ballot moves to A.
        // Round 2: A=3, B=2 -> A reaches a majority after two rounds.
        [, $ballot] = $this->finishedBallot(
            ['A', 'B', 'C'],
            [['A'], ['A'], ['B'], ['B'], ['C', 'A']],
        );

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.rankedchoice.winner_headline', ['name' => 'A']));
        $res->assertSeeText(__('components.rankedchoice.outcome_after_rounds', ['rounds' => 2, 'name' => 'A']));
    }

    public function test_round_detail_is_collapsed_behind_a_disclosure_by_default(): void
    {
        [, $ballot] = $this->finishedBallot(['A', 'B', 'C'], [['A'], ['A'], ['A']]);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        // The toggle is present...
        $res->assertSeeText(__('components.rankedchoice.how_counted'));
        // ...and the round-by-round block it wraps is hidden until clicked.
        $res->assertSee('x-show="open"', false);
        $res->assertSee('style="display: none;"', false);
        // The round table still renders inside that collapsed block (for auditors).
        $res->assertSeeText(__('components.rankedchoice.round') . ' 1');
    }

    public function test_quorum_not_met_shows_provisional_leader_not_a_winner(): void
    {
        // One vote, quorum of 10 -> not binding.
        [, $ballot] = $this->finishedBallot(['A', 'B'], [['A']], quorum: 10);

        $res = $this->fetchResult($ballot);
        $res->assertOk();
        $res->assertSeeText(__('components.rankedchoice.provisional_leader', ['name' => 'A']));
        $res->assertSeeText(__('components.rankedchoice.outcome_not_binding', ['name' => 'A', 'pct' => 100]));
        // No binding-winner sentence when the result is not binding. (The round-by-round
        // audit detail may still name a round winner — quorum is a separate ballot gate —
        // so we assert against the public outcome sentence, not the bare "Winner" word.)
        $res->assertDontSeeText(__('components.rankedchoice.outcome_majority', ['pct' => 100]));
    }
}
