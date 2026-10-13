<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Voting codes are minted exactly once per ballot: a second call must not add a second valid set. */
class GenerateCodesOnceTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        config(['app.api.authlist' => ['tenant-token']]);
    }

    /** @param array<string, mixed> $state */
    private function ballot(array $state = []): Ballot
    {
        $election = Election::factory()->create(['owner' => $this->owner]);

        return Ballot::factory()->create(array_merge(['election_id' => $election->id, 'is_secret' => true], $state));
    }

    /** @param array<string, mixed> $body */
    private function generate(Ballot $ballot, array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['Authorization' => 'tenant-token', 'Owner' => $this->owner])
            ->postJson("/api/election/$ballot->election_id/ballot/$ballot->id/vote/generate", $body);
    }

    public function test_second_generate_is_refused_with_409_and_mints_nothing(): void
    {
        $ballot = $this->ballot();

        $this->generate($ballot, ['quantity' => 3])->assertOk();
        $this->generate($ballot, ['quantity' => 3])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['error']);

        $this->assertSame(3, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_generate_on_a_ballot_that_already_has_codes_is_refused(): void
    {
        $ballot = $this->ballot();
        Vote::factory()->create(['ballot_id' => $ballot->id]);

        $this->generate($ballot, ['quantity' => 2])->assertStatus(409);
        $this->assertSame(1, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_generate_on_a_finished_ballot_is_refused(): void
    {
        $ballot = $this->ballot(['finished' => true]);

        $this->generate($ballot, ['quantity' => 2])->assertStatus(409);
        $this->assertSame(0, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_generate_still_works_on_an_active_ballot_without_codes(): void
    {
        $ballot = $this->ballot(['active' => true]);

        $this->generate($ballot, ['quantity' => 2])->assertOk()->assertJsonCount(2);
    }

    public function test_public_generate_is_also_once_only(): void
    {
        $ballot = $this->ballot(['is_secret' => false, 'mode' => Ballot::MODE_SESSION]);

        $this->generate($ballot, ['voters' => ['a@example.com']])->assertOk();
        $this->generate($ballot, ['voters' => ['b@example.com']])->assertStatus(409);
        $this->assertSame(1, Vote::where('ballot_id', $ballot->id)->count());
    }

    public function test_validation_errors_still_return_400(): void
    {
        $ballot = $this->ballot();

        $this->generate($ballot, [])->assertStatus(400);
    }
}
