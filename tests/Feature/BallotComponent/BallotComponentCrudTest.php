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

    /**
     * ApprovalVote top-K: `settings.seats` passes validation AND actually
     * persists on the model. Regression against the `buildSettings()`
     * blocker (D7 / Lane B): before its generalization, this 200'd but
     * silently dropped `seats` — the component stored no settings at all.
     */
    public function test_create_component_with_valid_seats_setting_passes_validation(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
            'settings' => ['seats' => 2],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertSame(['seats' => 2], $component->settings);
    }

    public function test_create_component_with_invalid_seats_setting_fails_validation(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        // Below the min:1 floor.
        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
            'settings' => ['seats' => 0],
        ])->assertJsonStructure(['field_errors' => ['settings.seats']]);

        // Non-integer.
        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
            'settings' => ['seats' => 'two'],
        ])->assertJsonStructure(['field_errors' => ['settings.seats']]);

        $this->assertSame(0, BallotComponent::where('ballot_id', $b->id)->count());
    }

    public function test_update_component_accepts_seats_setting(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $c = BallotComponent::factory()->create([
            'ballot_id' => $b->id,
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'options' => ['A', 'B', 'C'],
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Committee seats',
            'type' => 'ApprovalVote',
            'version' => 'v1',
            'settings' => ['seats' => 2],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $c->refresh();
        $this->assertSame(['seats' => 2], $c->settings);
    }

    /**
     * OrderedList's composition/gender quota (D-Lane-B, B0/B1): `settings.categories`
     * (a per-option label => category map) and `settings.quota`
     * ({category,type,count,binding}) both persist through the same
     * generalized `buildSettings()` that `seats`/`pass_threshold` use.
     */
    public function test_create_orderedlist_with_categories_and_quota_persists_both(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee',
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alice', 'Bob', 'Carol', 'Dave'],
            'settings' => [
                'seats' => 2,
                'categories' => ['Alice' => 'female', 'Carol' => 'female'],
                'quota' => ['category' => 'female', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertSame([
            'seats' => 2,
            'categories' => ['Alice' => 'female', 'Carol' => 'female'],
            'quota' => ['category' => 'female', 'type' => 'min', 'count' => 1, 'binding' => true],
        ], $component->settings);
    }

    /**
     * `binding` defaults to true when omitted, mirroring `OrderedList::parseQuota()`.
     */
    public function test_create_orderedlist_quota_defaults_binding_to_true(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee',
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
            'settings' => [
                'categories' => ['Alice' => 'female'],
                'quota' => ['category' => 'female', 'type' => 'max', 'count' => 0],
            ],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertTrue($component->settings['quota']['binding']);
    }

    /**
     * A malformed quota (missing the required `count`) is rejected at the
     * HTTP validation layer — a 422 with field errors, never a 500, and no
     * component is persisted.
     */
    public function test_create_orderedlist_with_malformed_quota_is_rejected_without_500(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee',
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
            'settings' => [
                'quota' => ['category' => 'female', 'type' => 'min'], // missing count
            ],
        ])->assertJsonStructure(['field_errors' => ['settings.quota.count']]);

        $this->assertSame(0, BallotComponent::where('ballot_id', $b->id)->count());
    }

    /**
     * A quota that is present but not an array at all must not 500 — dropped
     * defensively by `buildSettings()`'s second line of defense even though
     * the HTTP validation rule (`array`) would already reject it.
     */
    public function test_create_orderedlist_with_non_array_quota_is_rejected_without_500(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'Committee',
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
            'settings' => [
                'quota' => 'not-an-array',
            ],
        ])->assertJsonStructure(['field_errors' => ['settings.quota']]);

        $this->assertSame(0, BallotComponent::where('ballot_id', $b->id)->count());
    }

    /**
     * `update()` REPLACES settings wholesale (no merge): sending only `seats`
     * on an update must drop a previously-persisted `categories`/`quota`,
     * not leave them dangling. This is why the web_app editor must always
     * resend the full settings for the type on every save.
     */
    public function test_update_replaces_settings_wholesale_dropping_omitted_keys(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $c = BallotComponent::factory()->create([
            'ballot_id' => $b->id,
            'type' => 'OrderedList',
            'version' => 'v1',
            'options' => ['Alice', 'Bob'],
            'settings' => [
                'seats' => 1,
                'categories' => ['Alice' => 'female'],
                'quota' => ['category' => 'female', 'type' => 'min', 'count' => 1, 'binding' => true],
            ],
        ]);

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/$c->id", [
            'title' => 'Committee',
            'type' => 'OrderedList',
            'version' => 'v1',
            'settings' => ['seats' => 2],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $c->refresh();
        $this->assertSame(['seats' => 2], $c->settings);
    }

    /**
     * A YesNo component that sends a `settings` array with no usable key
     * (e.g. an explicit empty array, or pass_threshold null/empty) still
     * stores no settings at all — `buildSettings()` returns null, not `[]`,
     * exactly as before it was generalized.
     */
    public function test_create_yesno_with_empty_settings_array_leaves_settings_null(): void
    {
        [$e, $b, $req] = $this->ownedBallot();

        $req->postJson("/api/election/$e->id/ballot/$b->id/component/create", [
            'title' => 'No threshold component',
            'type' => 'YesNo',
            'version' => 'v1',
            'settings' => [],
        ])->assertJsonStructure(['data' => $this->component_schema]);

        $component = BallotComponent::where('ballot_id', $b->id)->firstOrFail();
        $this->assertNull($component->settings);
    }
}
