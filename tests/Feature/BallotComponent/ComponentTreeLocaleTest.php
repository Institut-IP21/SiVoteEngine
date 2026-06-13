<?php

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentTreeLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function listUrl(): string
    {
        $owner = Uuid::uuid();

        $election = Election::factory()
            ->state(['owner' => $owner])
            ->has(Ballot::factory()->state(['active' => true]))
            ->create();

        $ballot = $election->ballots[0];

        $this->owner = $owner;

        return "/api/election/{$election->id}/ballot/{$ballot->id}/component/";
    }

    public function test_component_tree_is_returned_in_english_when_requested(): void
    {
        $url = $this->listUrl();

        $response = $this->withHeaders([
            'Authorization' => '123123123',
            'Owner' => $this->owner,
            'Accept-Language' => 'en',
        ])->getJson($url);

        $response->assertSuccessful();
        $response->assertSee('First past the post', false);
        $response->assertDontSee('Izbira ene vrednosti', false);
    }

    public function test_component_tree_is_returned_in_slovenian_when_requested(): void
    {
        $url = $this->listUrl();

        $response = $this->withHeaders([
            'Authorization' => '123123123',
            'Owner' => $this->owner,
            'Accept-Language' => 'sl',
        ])->getJson($url);

        $response->assertSuccessful();
        $response->assertSee('Izbira ene vrednosti', false);
        $response->assertDontSee('First past the post', false);
    }
}
