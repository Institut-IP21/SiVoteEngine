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
        // The request-locale `strings` (back-compat, single-locale) reflect English —
        // scoped to that key rather than the whole body, since the additive
        // both-locale `i18n` field now legitimately carries Slovenian too.
        $this->assertSame('First past the post / Plurality question', $response->json('data.FirstPastThePost.v1.strings.name'));
        $this->assertNotSame('Izbira ene vrednosti izmed večih', $response->json('data.FirstPastThePost.v1.strings.name'));
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
        $this->assertSame('Izbira ene vrednosti izmed večih', $response->json('data.FirstPastThePost.v1.strings.name'));
        $this->assertNotSame('First past the post / Plurality question', $response->json('data.FirstPastThePost.v1.strings.name'));
    }

    /**
     * The component tree's metadata carries a both-locale `i18n.name`/`i18n.method`
     * pair alongside the existing request-locale `strings` — resolved explicitly in
     * BOTH locales regardless of the request's Accept-Language, for the
     * add-question modal's locale switcher (no second round trip needed).
     */
    public function test_component_tree_metadata_carries_both_locale_name_and_method_regardless_of_request_locale(): void
    {
        $url = $this->listUrl();

        foreach (['en', 'sl'] as $requestLocale) {
            $response = $this->withHeaders([
                'Authorization' => '123123123',
                'Owner' => $this->owner,
                'Accept-Language' => $requestLocale,
            ])->getJson($url);

            $response->assertSuccessful();
            $tree = $response->json('data');

            foreach (['FirstPastThePost', 'YesNo', 'RankedChoice', 'ApprovalVote', 'OrderedList'] as $type) {
                $i18n = $tree[$type]['v1']['i18n'];

                $this->assertNotSame('', trim((string) $i18n['name']['en']));
                $this->assertNotSame('', trim((string) $i18n['name']['sl']));
                $this->assertNotSame('', trim((string) $i18n['method']['en']));
                $this->assertNotSame('', trim((string) $i18n['method']['sl']));
                $this->assertNotSame($i18n['name']['en'], $i18n['name']['sl']);
            }
        }
    }
}
