<?php

namespace Tests\Feature\BallotComponent;

use App\BallotComponents\YesNo\v1\YesNo;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase-2 proof: YesNo's pass_threshold is operative end-to-end through a
 * PERSISTED component. Unlike the Phase-1 unit tests (make() + setAttribute),
 * these persist the `settings` column and reload it from the database, proving
 * the new migration + cast actually round-trip the threshold.
 */
class YesNoPassThresholdTest extends TestCase
{
    use RefreshDatabase;

    private function makeBallot(): Ballot
    {
        $election = Election::factory()->create();
        return Ballot::factory()->create(['election_id' => $election->id]);
    }

    /**
     * Persist a YesNo component on a real ballot, optionally with settings,
     * then re-fetch it fresh from the DB so we exercise the column + cast.
     *
     * @param array<string, mixed>|null $settings
     */
    private function persistComponent(Ballot $ballot, ?array $settings = null): BallotComponent
    {
        $factory = BallotComponent::factory()->state([
            'ballot_id' => $ballot->id,
            'type' => 'YesNo',
            'options' => ['yes', 'no'],
        ]);
        if ($settings !== null) {
            $factory = $factory->state(['settings' => $settings]);
        }
        $component = $factory->create();

        // Re-fetch fresh so we read `settings` back through the column + cast,
        // not from the in-memory instance.
        return BallotComponent::findOrFail($component->id);
    }

    /**
     * Persist $yes 'yes' votes and $no 'no' votes on the ballot, keyed to the
     * component id, then return them as a collection re-loaded from the DB.
     *
     * @return Collection<int, Vote>
     */
    private function persistVotes(Ballot $ballot, BallotComponent $component, int $yes, int $no): Collection
    {
        for ($i = 0; $i < $yes; $i++) {
            Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => 'yes']]);
        }
        for ($i = 0; $i < $no; $i++) {
            Vote::factory()->create(['ballot_id' => $ballot->id, 'values' => [$component->id => 'no']]);
        }
        return Vote::where('ballot_id', $ballot->id)->get();
    }

    public function test_persisted_two_thirds_threshold_blocks_simple_majority(): void
    {
        $ballot = $this->makeBallot();
        $component = $this->persistComponent($ballot, ['pass_threshold' => 'two_thirds']);

        // Sanity: the threshold actually round-tripped through the DB column.
        $this->assertSame(['pass_threshold' => 'two_thirds'], $component->settings);

        // 6 yes / 5 no: a simple majority (would pass at 50) but 54.5% < 2/3.
        $votes = $this->persistVotes($ballot, $component, 6, 5)->all();
        $r = YesNo::calculateResults($votes, $component);

        $this->assertSame(11, $r['valid_votes']);
        $this->assertSame('yes', $r['winner']);
        $this->assertFalse($r['passed']);
        $this->assertSame('two_thirds', $r['pass_threshold']);
    }

    public function test_persisted_numeric_70_threshold_blocks_simple_majority(): void
    {
        $ballot = $this->makeBallot();
        $component = $this->persistComponent($ballot, ['pass_threshold' => 70]);

        $this->assertSame(['pass_threshold' => 70], $component->settings);

        // 13 yes / 7 no = 65% < 70.
        $votes = $this->persistVotes($ballot, $component, 13, 7)->all();
        $r = YesNo::calculateResults($votes, $component);

        $this->assertSame(20, $r['valid_votes']);
        $this->assertSame('yes', $r['winner']);
        $this->assertFalse($r['passed']);
        $this->assertSame(70, $r['pass_threshold']);
    }

    public function test_persisted_no_settings_defaults_to_simple_majority_and_passes(): void
    {
        $ballot = $this->makeBallot();
        $component = $this->persistComponent($ballot);

        // No settings persisted -> null column -> default threshold 50.
        $this->assertNull($component->settings);

        // 6 yes / 5 no carries under the default simple majority.
        $votes = $this->persistVotes($ballot, $component, 6, 5)->all();
        $r = YesNo::calculateResults($votes, $component);

        $this->assertSame(11, $r['valid_votes']);
        $this->assertTrue($r['passed']);
        $this->assertSame(50, $r['pass_threshold']);
    }

    /**
     * The CLI normalises its --pass-threshold input (numeric -> int, presets
     * left as strings) before writing settings['pass_threshold'] via
     * BallotComponent::create(). This proves that normalised payload persists
     * and round-trips through the column + cast exactly as the tally reads it.
     *
     * The command's interactive prompt loop is not driven here: YesNo's options
     * step is a separate, pre-existing matter and not what this feature touches;
     * the create() call below is the persistence path BallotComponentCreate uses.
     *
     * @return iterable<string, array{0: string, 1: int|string}>
     */
    public static function cliThresholdProvider(): iterable
    {
        yield 'numeric normalised to int' => ['70', 70];
        yield 'preset string left as-is' => ['two_thirds', 'two_thirds'];
    }

    #[DataProvider('cliThresholdProvider')]
    public function test_cli_normalised_threshold_persists_and_round_trips(string $input, int|string $expected): void
    {
        $ballot = $this->makeBallot();

        // Replicate the command's normalisation (BallotComponentCreate::handle).
        $threshold = is_numeric($input) ? $input + 0 : $input;

        $created = BallotComponent::create([
            'ballot_id' => $ballot->id,
            'title' => 'CLI threshold motion',
            'description' => 'Persisted with a configured threshold',
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
            'settings' => ['pass_threshold' => $threshold],
        ]);

        // Re-fetch fresh so we read settings back through the column + cast.
        $component = BallotComponent::findOrFail($created->id);
        $this->assertSame(['pass_threshold' => $expected], $component->settings);

        // And it is operative: 6 yes / 5 no clears 50 but not a supermajority.
        $votes = $this->persistVotes($ballot, $component, 6, 5)->all();
        $r = YesNo::calculateResults($votes, $component);
        $this->assertSame($expected, $r['pass_threshold']);
        $this->assertFalse($r['passed']);
    }
}
