<?php

declare(strict_types=1);

namespace Tests\Feature\Ballot;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The top-right type label doubles as the trigger for an info modal giving the
 * lay ("how it works") explanation of that voting method — on BOTH the
 * voter-facing ballot and the public results page, since both render the
 * shared `x-ballot-component.title` component. As of the segmented-modal
 * restructure, the explanation is 2-3 short LABELED blocks ("How you vote" /
 * "How the result is decided" / optional "Good to know"), each sourced from
 * `components.<slug>.lay.<key>` + the shared `components.lay_labels`, rather
 * than one dense paragraph. `lay_explanation` (the join of those bodies)
 * survives only for back-compat / the API.
 */
class BallotComponentInfoModalTest extends TestCase
{
    use RefreshDatabase;

    /** Fixed segment count per type — mirrors AbstractBallotComponent::LAY_SEGMENT_ORDER minus any omitted `good_to_know`. */
    private const EXPECTED_SEGMENT_COUNTS = [
        'YesNo' => 3,
        'FirstPastThePost' => 2,
        'RankedChoice' => 2,
        'ApprovalVote' => 3,
        'OrderedList' => 3,
    ];

    private function ballotWith(string $type, array $options): Ballot
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => $type,
            'version' => 'v1',
            'options' => $options,
        ]);

        return $ballot;
    }

    public function test_ballot_preview_shows_an_accessible_info_trigger_and_lay_explanation(): void
    {
        $ballot = $this->ballotWith('ApprovalVote', ['X', 'Y', 'Z']);

        $response = $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");

        $response->assertOk();
        // A real <button> carrying aria-haspopup="dialog", not a plain span.
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        // The segmented lay explanation is present in the markup (inside the
        // x-cloak'd modal, which is a CSS-level hide, not absent from the HTML):
        // both the shared "how it works" heading and a stable phrase from its body.
        $response->assertSee(__('components.lay_labels.how_vote'));
        $response->assertSee(__('components.approval.lay.how_vote'));
    }

    public function test_result_page_shows_the_same_info_trigger_and_lay_explanation(): void
    {
        $election = Election::factory()->create();
        $ballot = Ballot::factory()->create(['election_id' => $election->id, 'finished' => true]);
        BallotComponent::factory()->create([
            'ballot_id' => $ballot->id,
            'type' => 'FirstPastThePost',
            'version' => 'v1',
            'options' => ['Ana', 'Betty'],
            'active' => true,
        ]);
        Vote::factory()->create([
            'ballot_id' => $ballot->id,
            'values' => [$ballot->components[0]->id => 'Ana'],
        ]);

        $response = $this->get("/election/{$election->id}/ballot/{$ballot->id}/result");

        $response->assertOk();
        $response->assertSee('aria-haspopup="dialog"', false);
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee(__('components.lay_labels.how_decided'));
        $response->assertSee(__('components.fptp.lay.how_decided'));
    }

    public function test_info_modal_never_uses_a_colored_left_border_accent_rail(): void
    {
        // Owner standing UI rule: no accent left-border rail on the modal/card.
        $ballot = $this->ballotWith('YesNo', ['yes', 'no']);

        $response = $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");

        $response->assertOk();
        $response->assertDontSee('border-left', false);
    }

    /**
     * The "How you vote" heading must precede its own body text in the
     * rendered HTML (labeled-block structure, not a shuffled/merged blob).
     */
    public function test_modal_renders_the_how_you_vote_heading_before_its_body(): void
    {
        $ballot = $this->ballotWith('YesNo', ['yes', 'no']);

        $response = $this->get("/election/{$ballot->election_id}/ballot/{$ballot->id}/preview");
        $html = $response->getContent();

        // Blade's `{{ }}` HTML-escapes output (e.g. the body's apostrophes),
        // so the needle must be escaped the same way `assertSee()` does.
        $headingPos = strpos((string) $html, e(__('components.lay_labels.how_vote')));
        $bodyPos = strpos((string) $html, e(__('components.yesno.lay.how_vote')));

        $this->assertNotFalse($headingPos, 'expected the "How you vote" heading in the modal markup');
        $this->assertNotFalse($bodyPos, 'expected the how_vote body text in the modal markup');
        $this->assertLessThan($bodyPos, $headingPos, 'the heading must precede its body');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function typeProvider(): array
    {
        return [
            'YesNo' => ['YesNo'],
            'FirstPastThePost' => ['FirstPastThePost'],
            'RankedChoice' => ['RankedChoice'],
            'ApprovalVote' => ['ApprovalVote'],
            'OrderedList' => ['OrderedList'],
        ];
    }

    /**
     * `lay_segments` must have the right count per type, in BOTH locales:
     * yesno=3, fptp=2, rankedchoice=2, approval=3, orderedlist=3 (the two
     * single-winner majority/plurality methods have no "good to know" caveat).
     *
     */
    #[DataProvider('typeProvider')]
    public function test_lay_segments_has_the_right_count_per_type_in_both_locales(string $type): void
    {
        $expected = self::EXPECTED_SEGMENT_COUNTS[$type];

        foreach (['en', 'sl'] as $locale) {
            App::setLocale($locale);

            $component = BallotComponent::factory()->make(['type' => $type, 'version' => 'v1']);

            $this->assertCount(
                $expected,
                $component->lay_segments,
                "{$type} should have {$expected} lay_segments in locale '{$locale}'"
            );

            foreach ($component->lay_segments as $segment) {
                $this->assertNotSame('', trim($segment['heading']));
                $this->assertNotSame('', trim($segment['body']));
            }

            // Back-compat: lay_explanation is exactly the segment bodies, space-joined.
            $joined = implode(' ', array_column($component->lay_segments, 'body'));
            $this->assertSame($joined, $component->lay_explanation);
        }
    }

    /**
     * EN/SL key parity: every type must expose the SAME set of `lay` segment
     * keys in both locales (no locale silently missing a "good_to_know").
     */
    public function test_lay_key_sets_match_between_locales_for_every_type(): void
    {
        $slugs = [
            'YesNo' => 'yesno',
            'FirstPastThePost' => 'fptp',
            'RankedChoice' => 'rankedchoice',
            'ApprovalVote' => 'approval',
            'OrderedList' => 'orderedlist',
        ];

        $en = require base_path('resources/lang/en/components.php');
        $sl = require base_path('resources/lang/sl/components.php');

        $this->assertSame(array_keys($en['lay_labels']), array_keys($sl['lay_labels']));

        foreach ($slugs as $slug) {
            $this->assertArrayHasKey('lay', $en[$slug], "en components.{$slug} must define 'lay'");
            $this->assertArrayHasKey('lay', $sl[$slug], "sl components.{$slug} must define 'lay'");
            $this->assertSame(
                array_keys($en[$slug]['lay']),
                array_keys($sl[$slug]['lay']),
                "components.{$slug}.lay key set must match between en/sl"
            );
        }
    }
}
