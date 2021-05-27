<?php

namespace Tests\Feature;

use App\Models\Election;
use Faker\Provider\Uuid;
use Tests\TestCase;

class BallotCrudTest extends TestCase
{
    public $ballot_schema = [
        'id',
        'election_id',
        'created_at',
        'updated_at',
        'title',
        'active',
        'votes_count',
        'finished',
        'description',
        'email_subject',
        'email_template',
        'components',
    ];

    public function test_auth_battery()
    {
        $el = Election::factory()
            ->hasBallots(3, [ 'active' => true ])
            ->create();

        $ballot = $el->ballots[0];

        $this->postJson("/api/election/$el->id/ballot/create")->assertUnauthorized();
        $this->getJson("/api/election/$el->id/ballot/$ballot->id")->assertUnauthorized();
        $this->postJson("/api/election/$el->id/ballot/$ballot->id")->assertUnauthorized();
        $this->deleteJson("/api/election/$el->id/ballot/$ballot->id")->assertUnauthorized();

        $this->getJson("/api/election/$el->id/ballot/$ballot->id/result")->assertUnauthorized();
        $this->getJson("/api/election/$el->id/ballot/$ballot->id/votes")->assertUnauthorized();
        $this->getJson("/api/election/$el->id/ballot/$ballot->id/votes.csv")->assertUnauthorized();
        $this->postJson("/api/election/$el->id/ballot/$ballot->id/activate")->assertUnauthorized();
        $this->postJson("/api/election/$el->id/ballot/$ballot->id/deactivate")->assertUnauthorized();

        $req = $this->withHeaders(['Authorization' => '123123123']);

        $owner_error = ['error' => 'No owner.'];

        $req->postJson("/api/election/$el->id/ballot/create")->assertForbidden()->assertJson($owner_error);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id")->assertForbidden()->assertJson($owner_error);
        $req->deleteJson("/api/election/$el->id/ballot/$ballot->id")->assertForbidden()->assertJson($owner_error);

        $req->getJson("/api/election/$el->id/ballot/$ballot->id/result")->assertForbidden()->assertJson($owner_error);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id/votes")->assertForbidden()->assertJson($owner_error);
        $req->getJson("/api/election/$el->id/ballot/$ballot->id/votes.csv")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id/activate")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id/deactivate")->assertForbidden()->assertJson($owner_error);
    }

    public function test_create_ballot_fails_without_required_params()
    {
        $el = Election::factory()->create();

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/create", [])
            ->assertStatus(400)
            ->assertJson([
                'error' => 'Request invalid.',
                'field_errors' => [
                    'title' => ['The title field is required.']
                ]
            ]);
    }

    public function test_get_ballot_success()
    {
        $el = Election::factory()
            ->hasBallots(3, [ 'active' => true ])
            ->create();

        foreach ($el->ballots as $ballot) {
            $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
            $req->getJson("/api/election/$el->id/ballot/$ballot->id")
                ->assertStatus(200)
                ->assertJsonStructure([
                    'data' => $this->ballot_schema
                ]);
        }
    }

    public function test_create_ballot()
    {
        $el = Election::factory()->create();

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/create", [
            'title' => 'My lovely Ballot',
            'description' => 'This Ballot is indeed incredible',
            'email_template' => 'Hello there Person, this is your email',
            'email_subject' => 'Message from the Testing process'
        ])
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => $this->ballot_schema
            ]);
    }

    public function test_update_ballot_fails_if_locked()
    {
        $el = Election::factory()
            ->hasBallots(1, [ 'active' => true ])
            ->hasBallots(1, [ 'finished' => true ])
            ->create();

        $ballotActive = $el->ballots[0];
        $ballotFinished = $el->ballots[1];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/$ballotActive->id", [])
            ->assertStatus(400);

        $req->postJson("/api/election/$el->id/ballot/$ballotFinished->id", [])
            ->assertStatus(400);
    }

    public function test_update_ballot_success()
    {
        $el = Election::factory()
            ->hasBallots(1, [ 'active' => false ])
            ->create();

        $ballot = $el->ballots[0];

        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $el->owner]);
        $req->postJson("/api/election/$el->id/ballot/$ballot->id", [
            'title' => 'I edited this title',
            'description' => 'I also edited the description',
            'email_template' => 'A new template, just for you',
            'email_subject' => 'A subject anew'
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => $this->ballot_schema
            ]);
    }
}
