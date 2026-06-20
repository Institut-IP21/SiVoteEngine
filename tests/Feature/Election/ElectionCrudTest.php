<?php

namespace Tests\Feature\Election;

use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCrudTest extends TestCase
{
    use RefreshDatabase;

    public $election_schema = [
        'id',
        'title',
        'description',
        'owner',
        'active',
        'locked',
        'created_at',
        'updated_at',
        'abstainable',
        'ballots',
    ];

    public function test_auth_battery(): void
    {
        $el = Election::factory()->create();
        $auth_error = ['error' => 'No authorization or invalid.'];

        $res = $this->getJson("/api/election/")->assertUnauthorized()->assertJson($auth_error);
        $this->postJson("/api/election/create")->assertUnauthorized()->assertJson($auth_error);
        $this->getJson("/api/election/$el->id")->assertUnauthorized()->assertJson($auth_error);
        $this->postJson("/api/election/$el->id")->assertUnauthorized()->assertJson($auth_error);
        $this->deleteJson("/api/election/$el->id")->assertUnauthorized()->assertJson($auth_error);

        $req = $this->withHeaders(['Authorization' => '123123123']);

        $owner_error = ['error' => 'No owner.'];

        $req->getJson("/api/election/")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/create")->assertForbidden()->assertJson($owner_error);
        $req->getJson("/api/election/$el->id")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/$el->id")->assertForbidden()->assertJson($owner_error);
        $req->deleteJson("/api/election/$el->id")->assertForbidden()->assertJson($owner_error);
    }

    public function test_get_election_fails_if_wrong_owner(): void
    {
        $el = Election::factory()->create(['owner' => UUID::uuid()]);
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => UUID::uuid()])
            ->getJson("/api/election/$el->id")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not own this.'
            ]);
    }

    public function test_get_election_success(): void
    {
        $owner = UUID::uuid();
        $el = Election::factory()->create([ 'owner' => $owner ]);
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner])
            ->getJson("/api/election/$el->id")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => $this->election_schema
            ]);
    }

    public function test_create_election_fails_without_required_fields(): void
    {
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => UUID::uuid()])
            ->postJson("/api/election/create")
            ->assertStatus(400)
            ->assertJson([
                "success" => false,
                "error" => "Request invalid.",
                "field_errors" => [
                    "title" => [
                        "The title field is required."
                    ]
                ]
            ]);
    }

    public function test_create_election_success(): void
    {
        $owner = UUID::uuid();
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner])
            ->postJson("/api/election/create", [
                'title' => 'My test Election',
                'abstainable' => true,
                'level' => 1,
            ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => $this->election_schema
            ])
            ->assertJson([
                'data' => [
                    'title' => 'My test Election',
                    'level' => 1,
                    'owner' => $owner
                ]
            ]);
    }

    public function test_update_election_fails_if_wrong_owner(): void
    {
        $el = Election::factory()->create();
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => UUID::uuid()])
            ->postJson("/api/election/$el->id", [
                'title' => 'My test Election updated',
                'abstainable' => false
            ])
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not own this.'
            ]);
    }

    public function test_update_election_success(): void
    {
        $el = Election::factory()->create();
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->postJson("/api/election/$el->id", [
                'title' => 'My test Election updated',
                'abstainable' => false
            ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => $this->election_schema
            ])
            ->assertJson([
                'data' => [
                    'title' => 'My test Election updated',
                    'owner' => $el->owner,
                    'abstainable' => false
                ]
            ]);
    }

    public function test_delete_election(): void
    {
        $el = Election::factory()->create();
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->deleteJson("/api/election/$el->id")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->getJson("/api/election/$el->id")
            ->assertStatus(404);
    }

    public function test_delete_election_blocked_when_ballot_open(): void
    {
        $el = Election::factory()->hasBallots(1, ['active' => true])->create();

        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->deleteJson("/api/election/$el->id")
            ->assertStatus(409)
            ->assertJson(['error' => 'Cannot delete an election while a ballot is open.']);

        // The election (and its open ballot) must still exist.
        $this->assertDatabaseHas('elections', ['id' => $el->id]);
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->getJson("/api/election/$el->id")
            ->assertStatus(200);
    }

    public function test_delete_election_allowed_when_ballot_closed(): void
    {
        // A finished (but not active) ballot must not block deletion.
        $el = Election::factory()->hasBallots(1, ['active' => false, 'finished' => true])->create();

        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->deleteJson("/api/election/$el->id")
            ->assertStatus(200);

        // Soft-deleted → no longer fetchable.
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->getJson("/api/election/$el->id")
            ->assertStatus(404);
    }
}
