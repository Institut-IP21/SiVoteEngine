<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use Faker\Provider\Uuid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ElectionLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_stores_organizer_locale_from_accept_language(): void
    {
        $owner = Uuid::uuid();

        $response = $this->withHeaders([
            'Authorization' => '123123123',
            'Owner' => $owner,
            'Accept-Language' => 'sl',
        ])->postJson('/api/election/create', [
            'title' => 'Test election',
            'level' => 1,
            'abstainable' => false,
        ]);

        $response->assertCreated();

        $election = Election::first();
        $this->assertSame('sl', $election->locale);
    }

    public function test_voter_facing_page_uses_election_locale_not_engine_default(): void
    {
        // Force the engine default to English so any Slovenian output must come
        // from the election's stored locale, not the app default.
        App::setLocale('en');

        $owner = Uuid::uuid();
        $election = Election::factory()
            ->state(['owner' => $owner, 'locale' => 'sl'])
            ->has(Ballot::factory()->state(['active' => true, 'finished' => false]))
            ->create();

        $ballot = $election->ballots[0];

        // Results aren't ready yet → 403 with the localized "not yet" message.
        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertStatus(403);
        $response->assertSee(__('ballot.result.not_yet', [], 'sl'), false);
    }

    public function test_voter_facing_page_falls_back_when_locale_null(): void
    {
        App::setLocale('en');

        $owner = Uuid::uuid();
        $election = Election::factory()
            ->state(['owner' => $owner, 'locale' => null])
            ->has(Ballot::factory()->state(['active' => true, 'finished' => false]))
            ->create();

        $ballot = $election->ballots[0];

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertStatus(403);
        // No election locale → engine default (en) is used.
        $response->assertSee(__('ballot.result.not_yet', [], 'en'), false);
    }
}
