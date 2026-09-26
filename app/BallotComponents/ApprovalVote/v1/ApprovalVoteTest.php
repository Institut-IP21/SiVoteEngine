<?php

declare(strict_types=1);

namespace App\BallotComponents\ApprovalVote\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Approval vote semantics (D1/D2/D9/D10) on his instance API + DTO ->toArray().
 * Expected values are our master ApprovalVoteTest, adapted to instance calls.
 */
class ApprovalVoteTest extends TestCase
{
    private ApprovalVote $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new ApprovalVote();
    }

    /**
     * @param array<int, string|array<int, mixed>>|string|null $answer
     */
    private function vote(BallotComponent $component, array|string|null $answer, bool $absent = false): Vote
    {
        $values = $absent ? [] : [$component->id => $answer];

        return Vote::factory()->make([
            'ballot_id' => $component->ballot_id,
            'values' => $values,
        ]);
    }

    private function makeComponent(string $o0 = 'A', string $o1 = 'B', string $o2 = 'C'): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'ApprovalVote',
            'options' => [$o0, $o1, $o2],
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed> $settings
     */
    private function makeComponentWithOptions(array $options, array $settings = []): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'ApprovalVote',
            'options' => $options,
            'settings' => $settings === [] ? null : $settings,
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    private function calc(array $votes, BallotComponent $component, bool $abstainable = false): array
    {
        return $this->component->calculateResults(new Collection($votes), $component, $abstainable)->toArray();
    }

    public function test_basic_multi_approval_unique_winner(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['B']),
            $this->vote($c, ['B', 'C']),
        ], $c);

        $this->assertEquals(['A' => 1, 'B' => 3, 'C' => 1], $r['state']);
        $this->assertEquals(3, $r['voters']);
        $this->assertEquals(5, $r['total_approvals']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals(0, $r['invalid']);
        $this->assertEquals(3, $r['total_ballots']);
        $this->assertEquals('B', $r['winner']);
        $this->assertEquals(['B'], $r['winners']);
    }

    public function test_full_roster_seeded_in_options_order(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([$this->vote($c, ['B'])], $c);

        $this->assertEquals(['A' => 0, 'B' => 1, 'C' => 0], $r['state']);
        $this->assertEquals(['A', 'B', 'C'], array_keys($r['state']));
    }

    public function test_two_way_tie(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['C']),
        ], $c);

        $this->assertEquals(['A' => 2, 'B' => 2, 'C' => 1], $r['state']);
        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['winners']);
    }

    public function test_n_way_tie(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A']),
            $this->vote($c, ['B']),
            $this->vote($c, ['C']),
        ], $c);

        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['winners']);
    }

    public function test_scalar_value_wrapped(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, 'A'),
            $this->vote($c, ['A', 'B']),
        ], $c);

        $this->assertEquals(['A' => 2, 'B' => 1, 'C' => 0], $r['state']);
        $this->assertEquals(2, $r['voters']);
        $this->assertEquals(3, $r['total_approvals']);
        $this->assertEquals('A', $r['winner']);
    }

    public function test_empty_set_is_participant(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A']),
            $this->vote($c, []),
        ], $c, true);

        $this->assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        $this->assertEquals(2, $r['voters']);
        $this->assertEquals(1, $r['total_approvals']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals(2, $r['total_ballots']);
        $this->assertEquals('A', $r['winner']);
    }

    public function test_true_abstention_not_a_voter(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A']),
            $this->vote($c, null, absent: true),
        ], $c, true);

        $this->assertEquals(2, $r['voters']);
        $this->assertEquals(1, $r['abstentions']);
        $this->assertEquals(3, $r['total_ballots']);
        $this->assertEquals('A', $r['winner']);
        $this->assertEquals(['A'], $r['winners']);
    }

    public function test_missing_or_null_is_invalid_when_not_abstainable(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A']),
            $this->vote($c, null, absent: true),
            $this->vote($c, null),
        ], $c, false);

        $this->assertEquals(1, $r['voters']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals(2, $r['invalid']);
        $this->assertEquals('A', $r['winner']);
    }

    public function test_empty_votes_no_winner(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([], $c);

        $this->assertEquals(['A' => 0, 'B' => 0, 'C' => 0], $r['state']);
        $this->assertEquals(0, $r['voters']);
        $this->assertEquals(0, $r['total_approvals']);
        $this->assertNull($r['winner']);
        $this->assertEquals([], $r['winners']);
    }

    public function test_all_abstain_no_winner(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
        ], $c, true);

        $this->assertEquals(0, $r['voters']);
        $this->assertEquals(2, $r['abstentions']);
        $this->assertNull($r['winner']);
        $this->assertEquals([], $r['winners']);
    }

    public function test_unknown_label_is_invalid(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'Z']),
            $this->vote($c, ['A']),
        ], $c);

        $this->assertEquals(['A' => 2, 'B' => 0, 'C' => 0], $r['state']);
        $this->assertEquals(2, $r['total_approvals']);
        $this->assertEquals(1, $r['invalid']);
        $this->assertEquals('A', $r['winner']);
        $this->assertNotContains('Z', $r['winners']);
    }

    public function test_non_scalar_approval_is_invalid(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', ['nested']]),
            $this->vote($c, ['A']),
        ], $c);

        $this->assertEquals(['A' => 2, 'B' => 0, 'C' => 0], $r['state']);
        $this->assertEquals(2, $r['total_approvals']);
        $this->assertEquals(1, $r['invalid']);
        $this->assertEquals('A', $r['winner']);
    }

    public function test_per_voter_denominator(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'B']),
        ], $c);

        $this->assertEquals(['A' => 2, 'B' => 2, 'C' => 0], $r['state']);
        $this->assertEquals(2, $r['voters']);
        $this->assertEquals(4, $r['total_approvals']);
        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['A', 'B'], $r['winners']);
    }

    public function test_winner_excludes_abstain_and_invalid(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'Z', 'Y']),
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
        ], $c, true);

        $this->assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        $this->assertEquals(1, $r['voters']);
        $this->assertEquals(3, $r['abstentions']);
        $this->assertEquals(2, $r['invalid']);
        $this->assertEquals('A', $r['winner']);
        $this->assertEquals(['A'], $r['winners']);
    }

    public function test_option_named_abstain_is_a_normal_winnable_option(): void
    {
        $c = $this->makeComponent('abstain', 'B', 'C');
        $r = $this->calc([
            $this->vote($c, ['abstain']),
            $this->vote($c, ['abstain']),
            $this->vote($c, ['B']),
        ], $c, true);

        $this->assertEquals(['abstain' => 2, 'B' => 1, 'C' => 0], $r['state']);
        $this->assertEquals(0, $r['abstentions']);
        $this->assertEquals('abstain', $r['winner']);
        $this->assertEquals(['abstain'], $r['winners']);
    }

    /**
     * D1/D3 top-K: with no `settings.seats`, K defaults to 1 and the result
     * is byte-identical to the pre-top-K single-winner shape — the hard
     * back-compat requirement.
     */
    public function test_seats_default_matches_pre_change_single_winner_shape(): void
    {
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['B']),
            $this->vote($c, ['B', 'C']),
        ], $c);

        $this->assertEquals(1, $r['seats']);
        $this->assertEquals('B', $r['winner']);
        $this->assertEquals(['B'], $r['winners']);
        $this->assertEquals(['B'], $r['elected']);
        $this->assertEquals([], $r['contested']);
        $this->assertEquals(0, $r['contested_seats']);
        $this->assertEquals([], $r['warnings']);
    }

    public function test_seats_two_elects_top_two_with_no_contest(): void
    {
        $c = $this->makeComponentWithOptions(['A', 'B', 'C', 'D'], ['seats' => 2]);
        $r = $this->calc([
            $this->vote($c, ['A', 'B', 'C', 'D']),
            $this->vote($c, ['A', 'B', 'C']),
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A']),
        ], $c);
        // A=4, B=3, C=2, D=1 — no ties, so the top-2 is clean.

        $this->assertEquals(2, $r['seats']);
        $this->assertEquals(['A', 'B'], $r['elected']);
        $this->assertEquals([], $r['contested']);
        $this->assertEquals(0, $r['contested_seats']);
    }

    public function test_seats_two_cutoff_tie_is_contested_not_elected(): void
    {
        $c = $this->makeComponentWithOptions(['A', 'B', 'C', 'E'], ['seats' => 2]);
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'C']),
            $this->vote($c, ['A', 'C']),
            $this->vote($c, ['B']),
            $this->vote($c, ['C']),
            $this->vote($c, ['E']),
            $this->vote($c, ['E']),
        ], $c);
        // A=4 (guaranteed), B=3 and C=3 genuinely tie for the 1 remaining seat.

        $this->assertEquals(['A'], $r['elected']);
        $this->assertEqualsCanonicalizing(['B', 'C'], $r['contested']);
        $this->assertEquals(1, $r['contested_seats']);
    }

    public function test_seats_requested_beyond_roster_is_clamped_with_a_warning(): void
    {
        $c = $this->makeComponentWithOptions(['A', 'B', 'C'], ['seats' => 10]);
        $r = $this->calc([
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A']),
        ], $c);

        $this->assertEquals(3, $r['seats']);
        $this->assertEquals(['A', 'B', 'C'], $r['elected']);
        $this->assertEquals([], $r['contested']);
        $this->assertEquals(['seats clamped to 3 (requested 10, roster has 3)'], $r['warnings']);
    }

    /**
     * The clamp warning must fire on config alone -- an unvoted preview of a
     * misconfigured seats value should not hide the problem until someone
     * actually votes.
     */
    public function test_seats_clamp_warning_fires_even_with_no_votes_cast(): void
    {
        $c = $this->makeComponentWithOptions(['A', 'B', 'C'], ['seats' => 10]);
        $r = $this->calc([], $c);

        $this->assertEquals(3, $r['seats']);
        $this->assertEquals([], $r['elected']);
        $this->assertEquals(['seats clamped to 3 (requested 10, roster has 3)'], $r['warnings']);
    }

    /**
     * A non-numeric `settings.seats` (e.g. a typo'd "abc") casts to 0, which
     * would misleadingly read as "requested 0" -- the operator never typed a
     * number at all, so the warning must say so instead.
     */
    public function test_non_numeric_seats_value_warns_without_a_misleading_requested_count(): void
    {
        $c = $this->makeComponentWithOptions(['A', 'B', 'C'], ['seats' => 'abc']);
        $r = $this->calc([
            $this->vote($c, ['A']),
        ], $c);

        $this->assertEquals(1, $r['seats']);
        $this->assertEquals(['seats clamped to 1 (invalid seats value, roster has 3)'], $r['warnings']);
    }

    public function test_all_voters_approve_every_option_is_a_full_tie(): void
    {
        // Degenerate maximum-approval case: every option saturates at the voter count.
        $c = $this->makeComponent();
        $r = $this->calc([
            $this->vote($c, ['A', 'B', 'C']),
            $this->vote($c, ['A', 'B', 'C']),
            $this->vote($c, ['A', 'B', 'C']),
        ], $c);

        $this->assertEquals(['A' => 3, 'B' => 3, 'C' => 3], $r['state']);
        $this->assertEquals(3, $r['voters']);
        $this->assertEquals(9, $r['total_approvals']);
        $this->assertEquals(0, $r['invalid']);
        $this->assertEquals('tie', $r['winner']);
        $this->assertEqualsCanonicalizing(['A', 'B', 'C'], $r['winners']);
    }

    public function test_values_to_csv(): void
    {
        $c = $this->makeComponent();
        $id = $c->id;

        $this->assertEquals('A, B, C', $this->component->valuesToCsv([$id => ['A', 'B', 'C']], $id));
        $this->assertEquals('', $this->component->valuesToCsv([], $id));
        $this->assertEquals('A', $this->component->valuesToCsv([$id => 'A'], $id));
    }

    public function test_submission_validator_rules(): void
    {
        $c = $this->makeComponent();
        $id = $c->id;

        $abstainable = Election::factory()->make(['abstainable' => true]);
        $rulesA = $this->component->getSubmissionValidator($c, $abstainable)->toArray();
        $this->assertEquals(['nullable', 'array'], $rulesA[$id]);
        $this->assertEquals(['distinct', Rule::in(['A', 'B', 'C'])], $rulesA["$id.*"]);

        $nonAbstainable = Election::factory()->make(['abstainable' => false]);
        $rulesB = $this->component->getSubmissionValidator($c, $nonAbstainable)->toArray();
        $this->assertEquals(['required', 'array'], $rulesB[$id]);
    }

    public function test_validate_options(): void
    {
        $this->assertTrue($this->component->validateOptions(['A', 'B']));
        $this->assertFalse($this->component->validateOptions(['A']));
        $this->assertFalse($this->component->validateOptions(['A', 'A']));
        $this->assertFalse($this->component->validateOptions(['A', '']));
    }

    public function test_get_statute_text_returns_nonempty_bilingual_paragraphs(): void
    {
        $statute = $this->component->getStatuteText();

        $this->assertSame('ApprovalVote', $statute->type);
        $this->assertNotEmpty($statute->en);
        $this->assertNotEmpty($statute->sl);
        foreach ([...$statute->en, ...$statute->sl] as $paragraph) {
            $this->assertNotSame('', trim($paragraph));
        }
    }

    public function test_get_statute_text_cutoff_tie_never_affirms_casting_vote_or_random_draw(): void
    {
        // Grounding fact (D1): a tie at the seat cutoff is surfaced, never
        // broken by a casting vote or a random draw performed by the system.
        $statute = $this->component->getStatuteText();
        $lower = strtolower(implode(' ', $statute->en));
        foreach ([
            'resolved by a casting vote', 'decided by a casting vote', 'broken by a casting vote',
            'resolved by a random draw', 'decided by a random draw', 'broken by a random draw',
        ] as $affirmation) {
            $this->assertStringNotContainsString($affirmation, $lower);
        }
    }
}
