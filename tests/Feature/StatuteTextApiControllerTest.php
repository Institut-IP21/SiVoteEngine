<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * GET /api/component-types/statute — the global, non-election-scoped bilingual
 * statute/legal-reference endpoint (statute-feature-spec.md §2.4). Behind the
 * shared-token ApiAuth only (no scope.bindings, no can:view,election) — same
 * auth pattern as `admin/stats` (AdminStatsTest).
 */
class StatuteTextApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.api.authlist' => [$this->token]]);
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        return ['Authorization' => $this->token, 'Owner' => (string) Str::uuid()];
    }

    public function test_missing_or_invalid_authorization_is_401(): void
    {
        $this->getJson('/api/component-types/statute')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);

        $this->withHeaders(['Authorization' => 'wrong-token', 'Owner' => (string) Str::uuid()])
            ->getJson('/api/component-types/statute')
            ->assertStatus(401)
            ->assertJson(['error' => 'No authorization or invalid.']);
    }

    public function test_valid_token_without_owner_header_is_403(): void
    {
        $this->withHeaders(['Authorization' => $this->token])
            ->getJson('/api/component-types/statute')
            ->assertStatus(403)
            ->assertJson(['error' => 'No owner.']);
    }

    public function test_returns_all_five_types_with_both_locales_and_the_quorum_preamble(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/component-types/statute');

        $response->assertOk()->assertJsonStructure([
            'YesNo' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'FirstPastThePost' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'RankedChoice' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'ApprovalVote' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'OrderedList' => ['name' => ['en', 'sl'], 'method' => ['en', 'sl'], 'statute' => ['en', 'sl']],
            'quorum' => ['en', 'sl'],
        ]);

        $body = $response->json();
        foreach (['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote', 'OrderedList'] as $type) {
            $this->assertNotSame('', trim((string) $body[$type]['name']['en']));
            $this->assertNotSame('', trim((string) $body[$type]['name']['sl']));
            $this->assertNotSame('', trim((string) $body[$type]['method']['en']));
            $this->assertNotSame('', trim((string) $body[$type]['method']['sl']));
            $this->assertNotEmpty($body[$type]['statute']['en']);
            $this->assertNotEmpty($body[$type]['statute']['sl']);
        }

        $this->assertNotEmpty($body['quorum']['en']);
        $this->assertNotEmpty($body['quorum']['sl']);
    }

    /**
     * The academic (neutral explanation + pros/cons, migrated from web_app's
     * academic.php) and lay (short voter-facing "how it works") content are
     * purely additive to this payload — web_app consumes both from here
     * instead of carrying its own copy.
     */
    public function test_returns_academic_and_lay_content_for_every_type_in_both_locales(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/component-types/statute');

        $response->assertOk()->assertJsonStructure([
            'YesNo' => [
                'academic' => ['en' => ['explanation', 'pros', 'cons'], 'sl' => ['explanation', 'pros', 'cons']],
                'lay' => ['en', 'sl'],
            ],
            'FirstPastThePost' => [
                'academic' => ['en' => ['explanation', 'pros', 'cons'], 'sl' => ['explanation', 'pros', 'cons']],
                'lay' => ['en', 'sl'],
            ],
            'RankedChoice' => [
                'academic' => ['en' => ['explanation', 'pros', 'cons'], 'sl' => ['explanation', 'pros', 'cons']],
                'lay' => ['en', 'sl'],
            ],
            'ApprovalVote' => [
                'academic' => ['en' => ['explanation', 'pros', 'cons'], 'sl' => ['explanation', 'pros', 'cons']],
                'lay' => ['en', 'sl'],
            ],
            'OrderedList' => [
                'academic' => ['en' => ['explanation', 'pros', 'cons'], 'sl' => ['explanation', 'pros', 'cons']],
                'lay' => ['en', 'sl'],
            ],
        ]);

        $body = $response->json();
        foreach (['YesNo', 'FirstPastThePost', 'RankedChoice', 'ApprovalVote', 'OrderedList'] as $type) {
            foreach (['en', 'sl'] as $locale) {
                $this->assertNotSame('', trim((string) $body[$type]['academic'][$locale]['explanation']));
                $this->assertNotEmpty($body[$type]['academic'][$locale]['pros']);
                $this->assertNotEmpty($body[$type]['academic'][$locale]['cons']);
                $this->assertNotSame('', trim((string) $body[$type]['lay'][$locale]));
            }
            $this->assertNotSame(
                $body[$type]['academic']['en']['explanation'],
                $body[$type]['academic']['sl']['explanation']
            );
            $this->assertNotSame($body[$type]['lay']['en'], $body[$type]['lay']['sl']);
        }
    }

    public function test_names_and_methods_differ_between_locales(): void
    {
        // A basic sanity check that en/sl are genuinely distinct payloads, not
        // the same string duplicated under both keys.
        $body = $this->withHeaders($this->authHeaders())
            ->getJson('/api/component-types/statute')
            ->json();

        $this->assertNotSame($body['YesNo']['name']['en'], $body['YesNo']['name']['sl']);
        $this->assertNotSame($body['YesNo']['statute']['en'], $body['YesNo']['statute']['sl']);
    }

    /**
     * `manual` (the by-hand calculation steps) and `comparison` (the
     * owner-approved comparison entry) are purely additive to this payload,
     * parallel to `academic`/`lay` — web_app's Voting methods guide (a
     * later lane) consumes both from here.
     */
    public function test_returns_manual_steps_and_comparison_for_every_type_in_both_locales(): void
    {
        $response = $this->withHeaders($this->authHeaders())->getJson('/api/component-types/statute');

        $response->assertOk()->assertJsonStructure([
            'YesNo' => [
                'manual' => ['en', 'sl'],
                'comparison' => ['elected' => ['en', 'sl'], 'ratings' => ['true_prefs', 'manipulation', 'simplicity']],
            ],
            'FirstPastThePost' => [
                'manual' => ['en', 'sl'],
                'comparison' => ['elected' => ['en', 'sl'], 'ratings' => ['true_prefs', 'manipulation', 'simplicity']],
            ],
            'RankedChoice' => [
                'manual' => ['en', 'sl'],
                'comparison' => ['elected' => ['en', 'sl'], 'ratings' => ['true_prefs', 'manipulation', 'simplicity']],
            ],
            'ApprovalVote' => [
                'manual' => ['en', 'sl'],
                'comparison' => ['elected' => ['en', 'sl'], 'ratings' => ['true_prefs', 'manipulation', 'simplicity']],
            ],
            'OrderedList' => [
                'manual' => ['en', 'sl'],
                'comparison' => ['elected' => ['en', 'sl'], 'ratings' => ['true_prefs', 'manipulation', 'simplicity']],
            ],
            'comparison_meta' => [
                'labels' => [
                    'elected' => ['en', 'sl'],
                    'true_prefs' => ['en', 'sl'],
                    'manipulation' => ['en', 'sl'],
                    'simplicity' => ['en', 'sl'],
                ],
                'disclaimer' => ['en', 'sl'],
            ],
        ]);

        $body = $response->json();

        $expectedRatings = [
            'YesNo' => ['true_prefs' => 2, 'manipulation' => 5, 'simplicity' => 5],
            'FirstPastThePost' => ['true_prefs' => 2, 'manipulation' => 2, 'simplicity' => 5],
            'ApprovalVote' => ['true_prefs' => 3, 'manipulation' => 3, 'simplicity' => 4],
            'RankedChoice' => ['true_prefs' => 4, 'manipulation' => 3, 'simplicity' => 3],
            'OrderedList' => ['true_prefs' => 5, 'manipulation' => 4, 'simplicity' => 2],
        ];

        foreach ($expectedRatings as $type => $ratings) {
            foreach (['en', 'sl'] as $locale) {
                $this->assertNotEmpty($body[$type]['manual'][$locale]);
                foreach ($body[$type]['manual'][$locale] as $step) {
                    $this->assertNotSame('', trim((string) $step));
                }
                $this->assertNotSame('', trim((string) $body[$type]['comparison']['elected'][$locale]));
            }
            $this->assertNotSame($body[$type]['manual']['en'], $body[$type]['manual']['sl']);
            $this->assertSame($ratings, $body[$type]['comparison']['ratings']);
        }

        // The owner-approved "number elected" descriptors, verbatim.
        $this->assertSame('Decision (pass/fail)', $body['YesNo']['comparison']['elected']['en']);
        $this->assertSame('Odločitev (sprejem/zavrnitev)', $body['YesNo']['comparison']['elected']['sl']);
        $this->assertSame('1', $body['FirstPastThePost']['comparison']['elected']['en']);
        $this->assertSame('1', $body['FirstPastThePost']['comparison']['elected']['sl']);
        $this->assertSame('Multiple (top K)', $body['ApprovalVote']['comparison']['elected']['en']);
        $this->assertSame('Več (najboljših K)', $body['ApprovalVote']['comparison']['elected']['sl']);
        $this->assertSame('1', $body['RankedChoice']['comparison']['elected']['en']);
        $this->assertSame('1', $body['RankedChoice']['comparison']['elected']['sl']);
        $this->assertSame('Multiple, ranked (K)', $body['OrderedList']['comparison']['elected']['en']);
        $this->assertSame('Več, razvrščeni (K)', $body['OrderedList']['comparison']['elected']['sl']);

        // comparison_meta: the shared column labels + disclaimer, both locales, distinct from each other.
        foreach (['elected', 'true_prefs', 'manipulation', 'simplicity'] as $label) {
            $this->assertNotSame('', trim((string) $body['comparison_meta']['labels'][$label]['en']));
            $this->assertNotSame('', trim((string) $body['comparison_meta']['labels'][$label]['sl']));
            $this->assertNotSame(
                $body['comparison_meta']['labels'][$label]['en'],
                $body['comparison_meta']['labels'][$label]['sl']
            );
        }
        $this->assertNotSame('', trim((string) $body['comparison_meta']['disclaimer']['en']));
        $this->assertNotSame('', trim((string) $body['comparison_meta']['disclaimer']['sl']));
        $this->assertNotSame($body['comparison_meta']['disclaimer']['en'], $body['comparison_meta']['disclaimer']['sl']);
    }
}
