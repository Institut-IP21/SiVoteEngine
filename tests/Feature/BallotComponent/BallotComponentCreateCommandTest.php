<?php

namespace Tests\Feature\BallotComponent;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the evote:make:ballot:component artisan command's option-resolution.
 *
 * Regression guard: a YesNo component ($needsOptions = false) must NOT enter the
 * option-prompt/validation loop. Its $optionsValidator is the scalar rule
 * ['options' => 'in:yes,no'], which an array of options can never satisfy — so
 * before the fix the loop prompted forever (and OOM'd under test mocking).
 * The command now uses the type's $presetOptions instead.
 */
class BallotComponentCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeBallot(): Ballot
    {
        $election = Election::factory()->create();
        return Ballot::factory()->create(['election_id' => $election->id]);
    }

    /**
     * Rebuild the command's confirmation prompt verbatim so expectsConfirmation
     * can match it. The command asks confirm("Please confirm the component: " .
     * print_r($args, true)) — see BallotComponentCreate::handle.
     *
     * @param array<string, mixed> $args
     */
    private function confirmQuestion(array $args): string
    {
        return 'Please confirm the component: ' . print_r($args, true);
    }

    public function test_yesno_component_is_created_with_preset_options_without_prompting(): void
    {
        $ballot = $this->makeBallot();

        // Every prompt-driving option is supplied, so the only thing that could
        // block this command is the (now-removed) YesNo option loop. With the fix
        // it goes straight to the confirmation prompt below, using preset options.
        $question = $this->confirmQuestion([
            'Ballot ID' => $ballot->id,
            'Title' => 'Adopt the budget',
            'Description' => 'A simple motion',
            'Component Type' => 'YesNo',
            'Version' => 'v1',
            'Options' => ['yes', 'no'],
            'Settings' => null,
        ]);

        $this->artisan('evote:make:ballot:component', [
            '--ballot' => $ballot->id,
            '--title' => 'Adopt the budget',
            '--description' => 'A simple motion',
            '--type' => 'YesNo',
            '--variant' => 'v1',
        ])
            // Belt-and-braces: the command must never report an option-loop failure.
            ->doesntExpectOutput('Not valid options for YesNo ballot type')
            ->expectsConfirmation($question, 'yes')
            ->assertExitCode(0);

        $component = BallotComponent::where('ballot_id', $ballot->id)
            ->where('type', 'YesNo')
            ->firstOrFail();

        $this->assertSame(['yes', 'no'], $component->options);
    }

    public function test_option_requiring_type_still_takes_supplied_options(): void
    {
        $ballot = $this->makeBallot();

        // FPTP has $needsOptions = true, so the --options flag must be honoured
        // and pass its array validator (>= 2 distinct non-empty strings).
        $question = $this->confirmQuestion([
            'Ballot ID' => $ballot->id,
            'Title' => 'Pick a chair',
            'Description' => 'Choose one',
            'Component Type' => 'FirstPastThePost',
            'Version' => 'v1',
            'Options' => ['Alice', 'Bob', 'Carol'],
            'Settings' => null,
        ]);

        $this->artisan('evote:make:ballot:component', [
            '--ballot' => $ballot->id,
            '--title' => 'Pick a chair',
            '--description' => 'Choose one',
            '--type' => 'FirstPastThePost',
            '--variant' => 'v1',
            '--options' => 'Alice, Bob, Carol',
        ])
            ->expectsConfirmation($question, 'yes')
            ->assertExitCode(0);

        $component = BallotComponent::where('ballot_id', $ballot->id)
            ->where('type', 'FirstPastThePost')
            ->firstOrFail();

        $this->assertSame(['Alice', 'Bob', 'Carol'], array_values($component->options));
    }
}
