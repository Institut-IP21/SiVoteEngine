<?php

namespace Tests\Feature\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_results_for_finished_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Finished Ballot',
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'President Vote',
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
        ]);

        // Create cast votes with real values
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Alice'],
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Alice'],
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$component->id => 'Bob'],
        ]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('President Vote')
            ->expectsOutputToContain('Alice')
            ->expectsOutputToContain('Bob')
            ->expectsOutputToContain('Total cast votes:')
            ->assertExitCode(0);
    }

    /**
     * A seats>1 ApprovalVote result must print the elected slate, not just
     * the single highest-count option (or "tie") that the old winner-only
     * line produced.
     */
    public function test_displays_elected_slate_for_multi_seat_approval_result(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Finished Ballot',
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C', 'D'],
            'settings' => ['seats' => 2],
        ]);

        // A=4, B=3, C=2, D=1 -- a clean top-2, no contest.
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'B', 'C', 'D']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'B', 'C']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'B']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A']]]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Committee seats')
            ->expectsOutputToContain('Elected: A, B')
            ->doesntExpectOutputToContain('Winner:')
            ->assertExitCode(0);
    }

    /**
     * A genuine cutoff tie must be reported as contested, never resolved
     * arbitrarily by the CLI output.
     */
    public function test_displays_contested_seats_for_a_cutoff_tie(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'title' => 'Finished Ballot',
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C', 'E'],
            'settings' => ['seats' => 2],
        ]);

        // A=4 (guaranteed), B=3 and C=3 genuinely tie for the 1 remaining seat.
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'B']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'B']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'C']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['A', 'C']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['B']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['C']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['E']]]);
        Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => ['E']]]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Elected: A')
            ->expectsOutputToContain('Contested for the last 1 seat(s):')
            ->assertExitCode(0);
    }

    public function test_errors_on_unfinished_ballot(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'finished' => false,
        ]);

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Results are only available for finished ballots')
            ->assertExitCode(1);
    }

    /**
     * An OrderedList prints its official slate (here a binding alternation
     * over a natural cut tied across groups), not a dump of the raw arrays.
     */
    public function test_displays_official_slate_for_ordered_list_result(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => false,
            'finished' => true,
        ]);
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'title' => 'Board list',
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['F1', 'M1', 'M2', 'M3', 'F2', 'F3'],
            'settings' => [
                'seats' => 3,
                'categories' => ['F1' => 'F', 'F2' => 'F', 'F3' => 'F', 'M1' => 'M', 'M2' => 'M', 'M3' => 'M'],
                'quota' => ['type' => 'alternate', 'binding' => true],
            ],
        ]);
        foreach ([['F1', 'M1', 'F2', 'F3', 'M2', 'M3'], ['F1', 'M1', 'F3', 'F2', 'M2', 'M3']] as $ranking) {
            Vote::factory()->forBallot($ballot)->withValues([$component->id => $ranking])->create();
        }

        $this->artisan('evote:result:ballot', ['--ballot' => $ballot->id])
            ->expectsOutputToContain('Board list')
            ->expectsTable(['Seat', 'Elected'], [[1, 'F1'], [2, 'M1']])
            ->expectsOutputToContain('Still tied for the open seats:')
            ->expectsOutputToContain('Final: no')
            ->assertExitCode(0);
    }
}
