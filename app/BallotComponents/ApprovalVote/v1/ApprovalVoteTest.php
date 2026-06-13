<?php

namespace App\BallotComponents\ApprovalVote\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertGreaterThan;

class ApprovalVoteTest extends TestCase
{
    /**
     * Build a Vote whose values hold the given answer at the component id.
     * Pass `null` for the answer to make a participant-with-null answer; pass
     * the special marker via $absent=true to omit the component key entirely.
     *
     * @param array<int, string|array<int, string>>|string|null $answer
     */
    private function vote(BallotComponent $component, array|string|null $answer, bool $absent = false): Vote
    {
        $values = $absent ? [] : [$component->id => $answer];

        return Vote::factory()->make([
            'ballot_id' => $component->ballot_id,
            'values' => $values,
        ]);
    }

    private function makeComponent(string $options0 = 'A', string $options1 = 'B', string $options2 = 'C'): BallotComponent
    {
        $ballot = Ballot::factory()->make();

        return BallotComponent::factory()->make([
            'type' => 'ApprovalVote',
            'options' => [$options0, $options1, $options2],
            'ballot' => $ballot,
        ]);
    }

    // AV-01 — Basic multi-approval, unique winner. Rates sum past 100%.
    public function test_basic_multi_approval_unique_winner(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['B']),
            $this->vote($c, ['B', 'C']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 1, 'B' => 3, 'C' => 1], $r['state']);
        assertEquals(3, $r['voters']);
        assertEquals(5, $r['total_approvals']);
        assertEquals(0, $r['abstentions']);
        assertEquals(0, $r['invalid']);
        assertEquals(3, $r['total_ballots']);
        assertEquals('B', $r['winner']);
        assertEquals(['B'], $r['winners']);
    }

    // Full roster: every option seeded at 0 in options order.
    public function test_full_roster_seeded_in_options_order(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [$this->vote($c, ['B'])];

        $r = ApprovalVote::calculateResults($votes, $c);

        // ordered A, B, C — all present, B=1 the rest 0
        assertEquals(['A' => 0, 'B' => 1, 'C' => 0], $r['state']);
        assertEquals(['A', 'B', 'C'], array_keys($r['state']));
    }

    // AV-02 — approve-all preserves the existing leader.
    public function test_approve_all_preserves_leader(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['B']),
            $this->vote($c, ['B']),
            $this->vote($c, ['A', 'B', 'C']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 1, 'B' => 3, 'C' => 1], $r['state']);
        assertEquals(3, $r['voters']);
        assertEquals(5, $r['total_approvals']);
        assertEquals('B', $r['winner']);
        assertEquals(['B'], $r['winners']);
    }

    // AV-05 — two-way tie lists both co-leaders, non-leaders excluded.
    public function test_two_way_tie(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['C']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 2, 'B' => 2, 'C' => 1], $r['state']);
        assertEquals('tie', $r['winner']);
        assertEquals(['A', 'B'], $r['winners']);
    }

    // AV-06 — three-way (N-way) tie: all co-leaders listed.
    public function test_n_way_tie(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A']),
            $this->vote($c, ['B']),
            $this->vote($c, ['C']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 1, 'B' => 1, 'C' => 1], $r['state']);
        assertEquals('tie', $r['winner']);
        assertEquals(['A', 'B', 'C'], $r['winners']);
        assertEquals(3, $r['voters']);
        assertEquals(3, $r['total_approvals']);
    }

    // AV-04 — scalar (non-array) value wrapped into a single approval.
    public function test_scalar_value_wrapped(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, 'A'),       // scalar
            $this->vote($c, ['A', 'B']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        // 'A' must be one approval, NOT iterated char-by-char.
        assertEquals(['A' => 2, 'B' => 1, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);
        assertEquals(3, $r['total_approvals']);
        assertEquals('A', $r['winner']);
    }

    // AV-08 / D10 — empty approval set is a participant who approved nobody.
    public function test_empty_set_is_participant(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A']),
            $this->vote($c, []),  // approves nobody — still a voter
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);          // empty-set voter counted
        assertEquals(1, $r['total_approvals']);
        assertEquals(0, $r['abstentions']);     // NOT an abstention
        assertEquals(2, $r['total_ballots']);
        assertEquals('A', $r['winner']);
    }

    // AV-07 / AV-14 — true abstention (absent key, abstainable): a non-voter.
    public function test_true_abstention_not_a_voter(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A']),
            $this->vote($c, null, absent: true),  // abstention
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 2, 'B' => 1, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);          // abstainer not a voter
        assertEquals(3, $r['total_approvals']);
        assertEquals(1, $r['abstentions']);
        assertEquals(3, $r['total_ballots']);   // voters + abstentions
        assertEquals('A', $r['winner']);
        assertEquals(['A'], $r['winners']);
    }

    // null value (key present) when abstainable counts as an abstention too.
    public function test_null_value_is_abstention_when_abstainable(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A']),
            $this->vote($c, null),  // key present, null value
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(1, $r['voters']);
        assertEquals(1, $r['abstentions']);
        assertEquals(2, $r['total_ballots']);
    }

    // D9 — on a NON-abstainable election, a missing/null answer is invalid (a
    // blank ballot), NOT a legitimate abstention. Counterpart to FPTP-16.
    public function test_missing_or_null_is_invalid_when_not_abstainable(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A']),
            $this->vote($c, null, absent: true),  // absent key
            $this->vote($c, null),                // key present, null value
        ];

        $r = ApprovalVote::calculateResults($votes, $c, false);

        assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(1, $r['voters']);
        assertEquals(0, $r['abstentions']);   // not abstentions on a non-abstainable election
        assertEquals(2, $r['invalid']);       // both blank ballots are invalid (D9)
        assertEquals(1, $r['total_approvals']);
        assertEquals('A', $r['winner']);
    }

    // D2 — per-voter rates: rows may sum PAST 100% because approvals overlap.
    public function test_per_voter_rates_sum_past_100_percent(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['B']),
            $this->vote($c, ['B', 'C']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        // voters = 3 (the per-voter-rate denominator, D2).
        assertEquals(3, $r['voters']);
        // rates: A = 1/3, B = 3/3 = 100%, C = 1/3 → the three rates sum to 5/3 > 100%.
        $rate = fn (string $opt): float => $r['state'][$opt] / $r['voters'] * 100;
        assertEquals(100.0, $rate('B'));
        assertGreaterThan(100.0, $rate('A') + $rate('B') + $rate('C'));
    }

    // AV-12 / AV-13 — empty votes array → full roster at 0, no winner, no ÷0.
    public function test_empty_votes_no_winner(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');

        $r = ApprovalVote::calculateResults([], $c);

        assertEquals(['A' => 0, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(0, $r['voters']);
        assertEquals(0, $r['total_approvals']);
        assertEquals(0, $r['abstentions']);
        assertEquals(0, $r['invalid']);
        assertEquals(0, $r['total_ballots']);
        assertEquals(null, $r['winner']);
        assertEquals([], $r['winners']);
    }

    // AV-13 — all abstain → no winner (not 'abstain').
    public function test_all_abstain_no_winner(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 0, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(0, $r['voters']);
        assertEquals(2, $r['abstentions']);
        assertEquals(2, $r['total_ballots']);
        assertEquals(null, $r['winner']);
        assertEquals([], $r['winners']);
    }

    // D9 — approval of an unknown label is invalid; real approvals unaffected.
    public function test_unknown_label_is_invalid(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'Z']),  // Z not in options
            $this->vote($c, ['A']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        // Z never appears in state; A counted twice.
        assertEquals(['A' => 2, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);
        assertEquals(2, $r['total_approvals']); // real approvals only
        assertEquals(1, $r['invalid']);          // the one unknown label
        assertEquals('A', $r['winner']);
        // invalid is never winnable
        $this->assertNotContains('Z', $r['winners']);
    }

    // D9 — a non-scalar where a label is expected → invalid, no TypeError.
    public function test_non_scalar_approval_is_invalid(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', ['nested']]),  // nested array element
            $this->vote($c, ['A']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 2, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(2, $r['total_approvals']);
        assertEquals(1, $r['invalid']);
        assertEquals('A', $r['winner']);
    }

    // AV-16 / D2 — per-voter denominator: 2 voters both [A,B] → A & B each 100%.
    public function test_per_voter_denominator(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['A', 'B']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c);

        assertEquals(['A' => 2, 'B' => 2, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);            // denominator = 2
        assertEquals(4, $r['total_approvals']);    // NOT the denominator
        assertEquals('tie', $r['winner']);
        assertEquals(['A', 'B'], $r['winners']);
        // each option's per-voter rate = 2/2 = 100%
        assertEquals(100.0, round(($r['state']['A'] / $r['voters']) * 100, 2));
        assertEquals(100.0, round(($r['state']['B'] / $r['voters']) * 100, 2));
    }

    // Winner excludes abstain + invalid even when they out-number real options.
    public function test_winner_excludes_abstain_and_invalid(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A', 'Z', 'Y']),  // A=1, Z/Y invalid (2)
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
            $this->vote($c, null, absent: true),
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(1, $r['voters']);
        assertEquals(1, $r['total_approvals']);
        assertEquals(3, $r['abstentions']);
        assertEquals(2, $r['invalid']);
        assertEquals(4, $r['total_ballots']);
        assertEquals('A', $r['winner']);
        assertEquals(['A'], $r['winners']);
    }

    // D2 consequence — an empty-set participant lowers other options' per-voter
    // rate: with 2 voters, A's rate is 1/2 = 50%, NOT 1/1 = 100%.
    public function test_empty_set_participant_lowers_per_voter_rate(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $votes = [
            $this->vote($c, ['A']),
            $this->vote($c, []),  // approves nobody — still a participant (D10/D2)
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        assertEquals(['A' => 1, 'B' => 0, 'C' => 0], $r['state']);
        assertEquals(2, $r['voters']);   // empty-set voter is in the denominator
        // A's per-voter rate is 1/2 = 50%, lowered honestly by the empty-set voter.
        assertEquals(50.0, ($r['state']['A'] / $r['voters']) * 100);
    }

    // Regression guard — an option literally named 'abstain' is a NORMAL winnable
    // option, NOT confused with an abstention (the old literal-token collision).
    public function test_option_named_abstain_is_a_normal_winnable_option(): void
    {
        $c = $this->makeComponent('abstain', 'B', 'C');
        $votes = [
            $this->vote($c, ['abstain']),
            $this->vote($c, ['abstain']),
            $this->vote($c, ['B']),
        ];

        $r = ApprovalVote::calculateResults($votes, $c, true);

        // 'abstain' is tallied as a real option and wins; not an abstention.
        assertEquals(['abstain' => 2, 'B' => 1, 'C' => 0], $r['state']);
        assertEquals(3, $r['voters']);
        assertEquals(3, $r['total_approvals']);
        assertEquals(0, $r['abstentions']);   // the label is NOT an abstention
        assertEquals(0, $r['invalid']);
        assertEquals('abstain', $r['winner']);
        assertEquals(['abstain'], $r['winners']);
    }

    // Anonymity / order-independence — the result depends only on the multiset
    // of approval sets, not on which voter cast which or in what order. Two vote
    // sets that are permutations of each other must produce identical results.
    public function test_result_is_order_independent(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');

        $set1 = [
            $this->vote($c, ['A', 'B']),
            $this->vote($c, ['C']),
        ];
        // Same multiset of approval sets, voters reordered and one set reversed.
        $set2 = [
            $this->vote($c, ['C']),
            $this->vote($c, ['B', 'A']),
        ];

        $r1 = ApprovalVote::calculateResults($set1, $c);
        $r2 = ApprovalVote::calculateResults($set2, $c);

        assertEquals($r1['state'], $r2['state']);
        assertEquals($r1['voters'], $r2['voters']);
        assertEquals($r1['total_approvals'], $r2['total_approvals']);
        assertEquals($r1['winner'], $r2['winner']);
        assertEquals($r1['winners'], $r2['winners']);
    }

    // valuesToCsv: array joins, missing key → '', and a scalar casts to array
    // (no TypeError).
    public function test_values_to_csv(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $id = $c->id;

        assertEquals('A, B, C', ApprovalVote::valuesToCsv([$id => ['A', 'B', 'C']], $id));
        assertEquals('', ApprovalVote::valuesToCsv([], $id));
        // scalar answer must not raise a TypeError in implode — it casts to array.
        assertEquals('A', ApprovalVote::valuesToCsv([$id => 'A'], $id));
    }

    // getSubmissionValidator: abstainable toggles nullable/required; 'id.*' is
    // Rule::in(options) and (unlike FPTP) 'abstain' is NOT appended.
    public function test_submission_validator_rules(): void
    {
        $c = $this->makeComponent('A', 'B', 'C');
        $id = $c->id;

        $abstainable = Election::factory()->make(['abstainable' => true]);
        $rulesA = ApprovalVote::getSubmissionValidator($c, $abstainable);
        // top-level field carries 'array' so a scalar submission can't bypass
        // the element rule below.
        assertEquals(['nullable', 'array'], $rulesA[$id]);
        assertEquals([Rule::in(['A', 'B', 'C'])], $rulesA["$id.*"]);
        // 'abstain' is NOT appended to the allowed options (unlike FPTP).
        $this->assertEquals(Rule::in(['A', 'B', 'C']), $rulesA["$id.*"][0]);

        $nonAbstainable = Election::factory()->make(['abstainable' => false]);
        $rulesB = ApprovalVote::getSubmissionValidator($c, $nonAbstainable);
        assertEquals(['required', 'array'], $rulesB[$id]);
    }

    // validateOptions: a valid ≥2 distinct-string list passes; too few, dupes,
    // and a non-array fail.
    public function test_validate_options(): void
    {
        $this->assertTrue(ApprovalVote::validateOptions(['A', 'B']));
        $this->assertFalse(ApprovalVote::validateOptions(['A']));        // min:2
        $this->assertFalse(ApprovalVote::validateOptions(['A', 'A']));   // distinct
        $this->assertFalse(ApprovalVote::validateOptions('A'));          // non-array
    }
}
