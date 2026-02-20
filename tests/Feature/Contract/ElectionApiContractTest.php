<?php

namespace Tests\Feature\Contract;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionApiContractTest extends TestCase
{
    use RefreshDatabase;

    private string $token = '123123123';
    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = fake()->uuid();
    }

    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => $this->owner];
    }

    public function test_create_election_response_has_data_wrapper_with_id(): void
    {
        $response = $this->postJson('/api/election/create', [
            'title' => 'Contract Test Election',
            'description' => 'Testing response shape',
            'level' => 1,
            'owner' => $this->owner,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => ['id', 'title', 'owner', 'description', 'active', 'level', 'locked', 'abstainable', 'ballots'],
        ]);
        $this->assertNotEmpty($response->json('data.id'));
    }

    public function test_get_election_ballots_keyed_by_id(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);

        $response = $this->getJson("/api/election/{$election->id}", $this->authHeaders());

        $response->assertSuccessful();

        $ballots = $response->json('data.ballots');
        $this->assertIsArray($ballots);
        // web_app accesses $election['ballots'][$ballot_id] -- ballots must be keyed by id
        $this->assertArrayHasKey($ballot->id, $ballots);
        $this->assertEquals($ballot->title, $ballots[$ballot->id]['title']);
    }

    public function test_activate_ballot_response_includes_email_fields(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'email_template' => 'Vote at %%LINK%%',
            'email_subject' => 'Please cast your vote',
        ]);

        $response = $this->postJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/activate",
            ['owner' => $this->owner],
            $this->authHeaders()
        );

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'data' => ['id', 'email_template', 'email_subject'],
        ]);
        $this->assertEquals('Vote at %%LINK%%', $response->json('data.email_template'));
        $this->assertEquals('Please cast your vote', $response->json('data.email_subject'));
    }

    public function test_generate_codes_returns_flat_uuid_array(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'is_secret' => true,
            'active' => true,
        ]);

        $response = $this->postJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/vote/generate",
            ['quantity' => 3, 'owner' => $this->owner],
            $this->authHeaders()
        );

        $response->assertSuccessful();

        $codes = $response->json();
        $this->assertIsArray($codes);
        $this->assertCount(3, $codes);
        // For secret ballots, codes should be flat UUIDs (strings)
        foreach ($codes as $code) {
            $this->assertIsString($code);
            $this->assertTrue(\Ramsey\Uuid\Uuid::isValid($code), "Code '$code' is not a valid UUID");
        }
    }

    public function test_generate_public_codes_returns_structured_objects(): void
    {
        $election = Election::factory()->create(['owner' => $this->owner]);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'is_secret' => false,
            'mode' => Ballot::MODE_SESSION,
            'active' => true,
        ]);

        $voters = ['alice@example.com', 'bob@example.com'];

        $response = $this->postJson(
            "/api/election/{$election->id}/ballot/{$ballot->id}/vote/generate",
            ['voters' => $voters, 'owner' => $this->owner],
            $this->authHeaders()
        );

        $response->assertSuccessful();

        $codes = $response->json();
        $this->assertIsArray($codes);
        $this->assertCount(2, $codes);
        // For public/session ballots, each entry should have code, voter, access_url
        foreach ($codes as $entry) {
            $this->assertArrayHasKey('code', $entry);
            $this->assertArrayHasKey('voter', $entry);
            $this->assertArrayHasKey('access_url', $entry);
        }
        $this->assertEquals('alice@example.com', $codes[0]['voter']);
        $this->assertEquals('bob@example.com', $codes[1]['voter']);
    }
}
