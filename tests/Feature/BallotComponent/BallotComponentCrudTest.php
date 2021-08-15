<?php

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Faker\Provider\Uuid;
use Tests\TestCase;

class BallotComponentCrudTest extends TestCase
{
    public $component_schema = [
        "id",
        "ballot_id",
        "title",
        "description",
        "type",
        "options",
        "version",
        "created_at",
        "updated_at",
        "slug"
    ];

    public function test_auth_battery()
    {
        $e = Election::factory()
            ->has(
                Ballot::factory()
                    ->state([ 'active' => true ])
                    ->has(BallotComponent::factory(), 'components')
            )
            ->create();

        $b = $e->ballots[0];
        $c = $b->components[0];

        $this->postJson("/api/election/$e->id/ballot/$b->id/component/create")->assertUnauthorized();
        $this->getJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertUnauthorized();
        $this->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertUnauthorized();
        $this->deleteJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertUnauthorized();

        $req = $this->withHeaders(['Authorization' => '123123123']);

        $owner_error = ['error' => 'No owner.'];

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create")->assertForbidden()->assertJson($owner_error);
        $req->getJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertForbidden()->assertJson($owner_error);
        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertForbidden()->assertJson($owner_error);
        $req->deleteJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertForbidden()->assertJson($owner_error);
    }

    public function test_get_component_success()
    {
        $owner = Uuid::uuid();
        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);

        $e = Election::factory()
            ->state([ 'owner' => $owner])
            ->has(
                Ballot::factory()
                    ->state([ 'active' => true ])
                    ->has(BallotComponent::factory(), 'components')
            )
            ->create();

        $b = $e->ballots[0];
        $c = $b->components[0];

        $req->getJson("/api/election/$e->id/ballot/$b->id/component/$c->id")->assertJsonStructure([
            'data' => $this->component_schema
        ]);
    }

    public function test_create_component_fails_without_valid_type_and_version()
    {
        $owner = Uuid::uuid();
        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);

        $e = Election::factory()
            ->state([ 'owner' => $owner])
            ->has(
                Ballot::factory()
                    ->state([ 'active' => true ])
            )
            ->create();

        $b = $e->ballots[0];

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'My testing component'
        ])->assertJson([
            'field_errors' => [
                'type' => ['The type field is required.'],
                'version' => ['The version field is required.']
            ]
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'My testing component',
            'type' => 'NonExistant',
            'version' => 'v8'
        ])->assertJson([
            'field_errors' => [
                'type' => ['type must be a valid ballot type.'],
                'version' => ['version must be a valid version.']
            ]
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'My testing component',
            'type' => 'YesNo',
            'version' => 'v222'
        ])->assertJson([
            'field_errors' => [
                'version' => ['version must be a valid version.']
            ]
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'My testing component',
            'type' => 'YesNo',
            'version' => 'v1'
        ])->assertJsonStructure([
            'data' => $this->component_schema
        ]);
    }
}
