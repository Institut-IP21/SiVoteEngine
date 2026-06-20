<?php

namespace Tests\Feature\Voting;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * #12 — the live "session" voter page renders inside the same redesigned shell
 * as the standalone ballot (x-ballot-wrapper / -title / -code / -component-card,
 * .ballot-submit), instead of the old .btn/.btn-blue layout.
 */
class SessionBallotRenderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, array<string, mixed>> $componentConfigs
     * @return array{0: Election, 1: Ballot, 2: array<int, BallotComponent>}
     */
    private function makeSession(array $componentConfigs): array
    {
        // Pin the organizer locale so the voter-facing copy renders in a known language.
        $election = Election::factory()->create(['abstainable' => false, 'locale' => 'en']);
        $ballot = Ballot::factory()->create([
            'election_id' => $election->id,
            'active' => true,
            'mode' => Ballot::MODE_SESSION,
        ]);
        $components = [];
        foreach ($componentConfigs as $c) {
            $components[] = BallotComponent::factory()->create(array_merge(['ballot_id' => $ballot->id], $c));
        }

        return [$election, $ballot, $components];
    }

    private function signedUrl(Election $election, Ballot $ballot, ?string $code = null): string
    {
        return URL::signedRoute('ballot.session', array_filter([
            'election' => $election->id,
            'ballot' => $ballot->id,
            'code' => $code,
        ]));
    }

    public function test_renders_redesigned_shell_with_active_component_and_submit(): void
    {
        [$election, $ballot] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Adopt the bylaws?', 'options' => ['yes', 'no'], 'active' => true],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->get($this->signedUrl($election, $ballot, $vote->id));

        $response->assertOk();
        // Redesigned shell + design-system submit button, not the legacy .btn-blue.
        $response->assertSee('ballot-submit', false);
        $response->assertSee('Cast vote');
        $response->assertSee('Adopt the bylaws?');
        $response->assertDontSee('btn-blue', false);
        // Anonymity footer from the shared shell copy.
        $response->assertSee('Your vote will be recorded anonymously.');
    }

    public function test_preview_mode_hides_submit_button(): void
    {
        [$election, $ballot] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => ['yes', 'no'], 'active' => true],
        ]);

        // No code → preview mode: questions render but the submit action is withheld.
        $response = $this->get($this->signedUrl($election, $ballot));

        $response->assertOk();
        $response->assertDontSee('ballot-submit', false);
    }

    public function test_empty_state_when_no_questions_are_open(): void
    {
        // A ballot whose only component is inactive shows the "nothing open right now" copy.
        [$election, $ballot] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Closed Q', 'options' => ['yes', 'no'], 'active' => false],
        ]);
        $vote = Vote::factory()->forBallot($ballot)->create();

        $response = $this->get($this->signedUrl($election, $ballot, $vote->id));

        $response->assertOk();
        $response->assertSee('No questions are open right now. This page updates automatically when the next one opens.');
        $response->assertDontSee('ballot-submit', false);
    }

    public function test_unsigned_request_is_rejected(): void
    {
        [$election, $ballot] = $this->makeSession([
            ['type' => 'YesNo', 'version' => 'v1', 'title' => 'Q', 'options' => ['yes', 'no'], 'active' => true],
        ]);

        $this->get(route('ballot.session', ['election' => $election->id, 'ballot' => $ballot->id]))
            ->assertStatus(401);
    }
}
