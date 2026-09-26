<?php

declare(strict_types=1);

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the `runner_resolutions` column + cast round-trips through the DB
 * (Task 9): a component can be created without it (defaults to null), and a
 * persisted resolution list is readable, unchanged, after a fresh fetch.
 */
class RunnerResolutionsColumnTest extends TestCase
{
    use RefreshDatabase;

    private function makeBallot(): Ballot
    {
        $election = Election::factory()->create();

        return Ballot::factory()->create(['election_id' => $election->id]);
    }

    public function test_runner_resolutions_defaults_to_null(): void
    {
        $ballot = $this->makeBallot();
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'options' => ['A', 'B', 'C'],
        ]);

        $fresh = BallotComponent::findOrFail($component->id);

        $this->assertNull($fresh->runner_resolutions);
    }

    public function test_runner_resolutions_round_trips_through_the_column_and_cast(): void
    {
        $ballot = $this->makeBallot();
        $component = BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'OrderedList',
            'options' => ['A', 'B', 'C'],
        ]);

        $resolutions = [
            [
                'cluster' => ['B', 'C'],
                'order' => ['B', 'C'],
                'comment' => 'Runner-off coin toss witnessed by both agents.',
                'resolved_by' => 'returning-officer',
                'resolved_at' => '2026-09-26T10:00:00+00:00',
            ],
        ];

        $component->runner_resolutions = $resolutions;
        $component->save();

        $fresh = BallotComponent::findOrFail($component->id);

        // assertEquals, not assertSame: MySQL's JSON column type does not
        // guarantee object key order is preserved, only the key/value pairs.
        $this->assertEquals($resolutions, $fresh->runner_resolutions);
    }
}
