<?php

namespace Tests\Feature\Security;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteCodeExposureTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        config(['app.api.authlist' => ['tenant-token']]);
        config(['app.api.admin_authlist' => ['admin-secret']]);
    }

    /**
     * @param array<string, mixed> $state
     * @return array{0: Election, 1: Ballot, 2: array<int, string>}
     */
    private function ballotWithCodes(array $state, int $count = 3): array
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create(array_merge(['election_id' => $election->id], $state));
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = Vote::factory()->create(['ballot_id' => $ballot->id])->id;
        }

        return [$election, $ballot, $ids];
    }

    /** @return array<string, string> */
    private function tenant(): array
    {
        return ['Authorization' => 'tenant-token', 'Owner' => $this->owner];
    }

    public function test_draft_ballot_returns_raw_codes(): void
    {
        [$e, $b, $ids] = $this->ballotWithCodes(['active' => false, 'finished' => false]);

        $res = $this->withHeaders($this->tenant())
            ->getJson("/api/election/$e->id/ballot/$b->id/vote")
            ->assertOk();

        $returned = $res->json();
        $this->assertIsArray($returned);
        sort($ids);
        $sorted = $returned;
        sort($sorted);
        $this->assertSame($ids, $sorted);
    }

    public function test_active_ballot_returns_counts_not_codes(): void
    {
        [$e, $b, $ids] = $this->ballotWithCodes(['active' => true, 'finished' => false]);

        $res = $this->withHeaders($this->tenant())
            ->getJson("/api/election/$e->id/ballot/$b->id/vote")
            ->assertOk()
            ->assertJsonStructure(['electorate_size', 'votes_count'])
            ->assertJsonPath('electorate_size', 3);

        foreach ($ids as $id) {
            $this->assertStringNotContainsString($id, $res->getContent() ?: '');
        }
    }

    public function test_finished_ballot_returns_counts_not_codes(): void
    {
        [$e, $b, $ids] = $this->ballotWithCodes(['active' => false, 'finished' => true]);

        $res = $this->withHeaders($this->tenant())
            ->getJson("/api/election/$e->id/ballot/$b->id/vote")
            ->assertOk()
            ->assertJsonStructure(['electorate_size', 'votes_count']);

        foreach ($ids as $id) {
            $this->assertStringNotContainsString($id, $res->getContent() ?: '');
        }
    }

    public function test_admin_token_can_still_read_codes_on_active_ballot(): void
    {
        [$e, $b, $ids] = $this->ballotWithCodes(['active' => true, 'finished' => false]);

        $res = $this->withHeaders(['Authorization' => 'admin-secret', 'Owner' => $this->owner])
            ->getJson("/api/election/$e->id/ballot/$b->id/vote")
            ->assertOk();

        $returned = $res->json();
        $this->assertIsArray($returned);
        $this->assertCount(3, $returned);
    }

    public function test_generate_flow_still_returns_codes(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'is_secret' => true,
            'active' => false,
        ]);

        $res = $this->withHeaders($this->tenant())
            ->postJson("/api/election/$election->id/ballot/$ballot->id/vote/generate", ['quantity' => 4])
            ->assertOk();

        $codes = $res->json();
        $this->assertIsArray($codes);
        $this->assertCount(4, $codes);
        foreach ($codes as $code) {
            $this->assertTrue(Uuid::isValid((string) $code));
        }
    }
}
