<?php

namespace Tests\Feature;

use App\Models\Election;
use Faker\Provider\Uuid;
use Tests\TestCase;

class ElectionCrudTest extends TestCase
{
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

    public function test_auth_battery()
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

    public function test_get_election_fails_if_wrong_owner()
    {
        $el = Election::factory()->create(['owner' => UUID::uuid()]);
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => UUID::uuid()])
            ->getJson("/api/election/$el->id")
            ->assertStatus(403)
            ->assertJson([
                'message' => 'You do not own this.'
            ]);
    }

    public function test_get_election_success()
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

    public function test_create_election_fails_without_required_fields()
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

    public function test_create_election_success()
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

    public function test_update_election_fails_if_wrong_owner()
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

    public function test_update_election_success()
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

    public function test_delete_election()
    {
        $el = Election::factory()->create();
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->deleteJson("/api/election/$el->id")
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner])
            ->getJson("/api/election/$el->id")
            ->assertStatus(404);
    }
}
