<?php

namespace Tests\Feature\BallotComponent;

use Illuminate\Testing\TestResponse;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BallotComponentCrudTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_auth_battery(): void
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

    public function test_get_component_success(): void
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

    public function test_create_component_fails_without_valid_type_and_version(): void
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

    /**
     * An owned, authenticated ballot context: returns [election, ballot, request].
     *
     * @return array{0: Election, 1: Ballot, 2: TestResponse|\Illuminate\Foundation\Testing\TestCase|TestCase}
     */
    private function ownedBallot(): array
    {
        $owner = Uuid::uuid();
        $e = Election::factory()
            ->state(['owner' => $owner])
            ->has(Ballot::factory()->state(['active' => false]))
            ->create();
        $b = $e->ballots[0];
        $req = $this->withHeaders(['Authorization' => '123123123', 'Owner' => $owner]);

        return [$e, $b, $req];
    }

    public function test_create_component_with_preset_pass_threshold(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Threshold component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => ['pass_threshold' => 'two_thirds'],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertSame('two_thirds', $component->settings['pass_threshold']);
    }

    public function test_create_component_with_numeric_pass_threshold_persists_as_number(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Threshold component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => ['pass_threshold' => 70],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertSame(70, $component->settings['pass_threshold']);
    }

    public function test_create_component_with_invalid_pass_threshold_fails(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        // Numeric below the [50,100] range.
        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Threshold component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => ['pass_threshold' => 40],
        ])->assertJsonStructure(['field_errors' => ['settings.pass_threshold']]);

        // Unknown preset string.
        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Threshold component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => ['pass_threshold' => 'three_fifths'],
        ])->assertJsonStructure(['field_errors' => ['settings.pass_threshold']]);

        $this->assertSame(0, BallotComponent::where('ballot_id', $b->id)->count());
    }

    public function test_create_component_without_settings_leaves_settings_null(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'No settings component',
            'type' => 'YesNo',
            'version' => 'v1',
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertNull($component->settings);
    }

    public function test_update_component_toggles_pass_threshold(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $c = BallotComponent::factory()->create([
            'ballot_id' => $b->id,
            'type' => 'YesNo',
            'version' => 'v1',
            'options' => ['yes', 'no'],
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Updated component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => ['pass_threshold' => 'three_quarters'],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $c->refresh();
        $this->assertSame('three_quarters', $c->settings['pass_threshold']);
    }
}
