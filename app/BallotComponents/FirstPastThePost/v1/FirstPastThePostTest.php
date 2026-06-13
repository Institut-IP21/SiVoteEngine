<?php

namespace App\BallotComponents\FirstPastThePost\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class FirstPastThePostTest extends TestCase
{
    /**
     * @param array<int, mixed> $rawAnswers each entry is the stored answer for one vote
     *   (a scalar option, the 'abstain' token, an array, etc.) — or the sentinel
     *   '__absent__' to omit the component key entirely, or null for a null values map.
     * @return array<int, Vote>
     */
    private function votesFor(BallotComponent $component, array $rawAnswers): array
    {
        $ballot = Ballot::factory()->make();

        return collect($rawAnswers)->map(function (mixed $answer) use ($component, $ballot): Vote {
            if ($answer === '__absent__') {
                $values = [];
            } elseif ($answer === '__nullmap__') {
                $values = null;
            } else {
                $values = [$component->id => $answer];
            }

            return Vote::factory()->make([
                'ballot_id' => $ballot->id,
                'values' => $values,
            ]);
        })->all();
    }

    private function fptpComponent(): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
        ]);
    }

    // ---- Validator (unchanged behaviour, kept) ----------------------------

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $component = $this->fptpComponent();
        $validator = FirstPastThePost::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => [
                'required',
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest']),
            ],
        ], $validator);
    }

    // ---- Clear winner: full contract --------------------------------------

    public function test_clear_winner_full_contract(): void
    {
        // FPTP-01: Ana x3, Betty x2, Charles x1; David/Ernest get 0 (full roster, D10).
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Ana', 'Betty', 'Betty', 'Charles',
        ]);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals([
            'Ana' => 3,
            'Betty' => 2,
            'Charles' => 1,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
        assertEquals(6, $result['valid_votes']);
        assertEquals(0, $result['abstentions']);
        assertEquals(0, $result['invalid']);
        assertEquals(6, $result['total_votes']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
    }

    // ---- D4: plurality without majority wins ------------------------------

    public function test_plurality_without_majority_wins(): void
    {
        // FPTP-02 / D4 guard: Ana 4/10 = 40% wins, no majority gate.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Ana', 'Ana',
            'Betty', 'Betty', 'Betty',
            'Charles', 'Charles', 'Charles',
        ]);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(10, $result['valid_votes']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
    }

    // ---- Ties -------------------------------------------------------------

    public function test_two_way_tie_for_the_lead(): void
    {
        // Ana 2, Betty 2, Charles 1 -> tie between Ana & Betty; Charles excluded.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Betty', 'Betty', 'Charles',
        ]);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals('tie', $result['winner']);
        assertEquals(['Ana', 'Betty'], $result['winners']);
        assertEquals(5, $result['valid_votes']);
    }

    public function test_n_way_all_equal_tie(): void
    {
        // Ana 1, Betty 1, Charles 1 -> all three co-leaders.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', 'Betty', 'Charles']);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals('tie', $result['winner']);
        assertEquals(['Ana', 'Betty', 'Charles'], $result['winners']);
    }

    // ---- Empty votes: full roster all zero, no winner ---------------------

    public function test_empty_votes_full_roster_no_winner(): void
    {
        // FPTP-06: zero ballots -> every option seeded 0, no winner, no div-by-zero.
        $component = $this->fptpComponent();
        $result = FirstPastThePost::calculateResults([], $component);

        assertEquals([
            'Ana' => 0,
            'Betty' => 0,
            'Charles' => 0,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
        assertEquals(0, $result['valid_votes']);
        assertEquals(0, $result['abstentions']);
        assertEquals(0, $result['invalid']);
        assertEquals(0, $result['total_votes']);
        assertEquals(null, $result['winner']);
        assertEquals([], $result['winners']);
    }

    // ---- D10: full roster, zero-vote options shown at 0, ordered ----------

    public function test_state_ordered_by_options_with_zero_rows(): void
    {
        // Votes arrive in non-options order; state must follow options order and
        // include zero-vote options.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Charles', 'Ana', 'Ana']);

        $result = FirstPastThePost::calculateResults($votes, $component);

        // assertSame on keys order: array_keys preserves insertion order.
        assertEquals(['Ana', 'Betty', 'Charles', 'David', 'Ernest'], array_keys($result['state']));
        assertEquals([
            'Ana' => 2,
            'Betty' => 0,
            'Charles' => 1,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
    }

    // ---- D1: abstentions separate, excluded from denominator --------------

    public function test_abstention_separate_when_abstainable(): void
    {
        // FPTP-15: Ana x2, Betty x1, abstain x1 (abstainable).
        // Ana share = 2/3 (valid_votes=3), not 2/4. Abstain reported separately.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', 'Ana', 'Betty', 'abstain']);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals([
            'Ana' => 2,
            'Betty' => 1,
            'Charles' => 0,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
        assertEquals(3, $result['valid_votes']);
        assertEquals(1, $result['abstentions']);
        assertEquals(0, $result['invalid']);
        assertEquals(4, $result['total_votes']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
        // abstain is NOT a state key (it is a separate tally, not an option row).
        assertEquals(false, array_key_exists('abstain', $result['state']));
    }

    public function test_abstain_never_in_winners_even_if_most_frequent(): void
    {
        // FPTP-13/14: abstain x3, Ana x1, Betty x1 (abstainable).
        // abstain leads in raw count but can never win; real options tie.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['abstain', 'abstain', 'abstain', 'Ana', 'Betty']);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals(2, $result['valid_votes']);
        assertEquals(3, $result['abstentions']);
        assertEquals('tie', $result['winner']);
        assertEquals(['Ana', 'Betty'], $result['winners']);
        assertEquals(false, in_array('abstain', $result['winners'], true));
    }

    // ---- D9: abstain token on non-abstainable election is invalid ---------

    public function test_abstain_token_on_non_abstainable_is_invalid(): void
    {
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', 'abstain']);

        // abstainable defaults to false.
        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(1, $result['valid_votes']);
        assertEquals(0, $result['abstentions']);
        assertEquals(1, $result['invalid']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
    }

    // ---- D9: missing/null is abstain (abstainable) / invalid (non) --------

    public function test_missing_value_is_abstain_when_abstainable(): void
    {
        // FPTP-11/12: absent component key & null values map -> abstain when abstainable.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', '__absent__', '__nullmap__']);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals(1, $result['valid_votes']);
        assertEquals(2, $result['abstentions']);
        assertEquals(0, $result['invalid']);
        assertEquals('Ana', $result['winner']);
    }

    public function test_missing_value_is_invalid_when_non_abstainable(): void
    {
        // FPTP-16/D9: blank/missing on a non-abstainable election is invalid, not abstain.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', '__absent__', '__nullmap__']);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(1, $result['valid_votes']);
        assertEquals(0, $result['abstentions']);
        assertEquals(2, $result['invalid']);
        assertEquals('Ana', $result['winner']);
    }

    // ---- D9: out-of-options is invalid, cannot win ------------------------

    public function test_out_of_options_is_invalid_and_cannot_win(): void
    {
        // FPTP-18: a stale/tampered label 'Zelda' appears most often but is invalid.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Zelda', 'Zelda', 'Zelda', 'Ana']);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals([
            'Ana' => 1,
            'Betty' => 0,
            'Charles' => 0,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
        assertEquals(1, $result['valid_votes']);
        assertEquals(3, $result['invalid']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
        assertEquals(false, array_key_exists('Zelda', $result['state']));
    }

    // ---- D9: empty-string is invalid --------------------------------------

    public function test_empty_string_is_invalid(): void
    {
        // FPTP-17: '' is not in options -> invalid (not abstain, not its own row).
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', '']);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals(1, $result['valid_votes']);
        assertEquals(0, $result['abstentions']);
        assertEquals(1, $result['invalid']);
        assertEquals('Ana', $result['winner']);
    }

    // ---- D9: array value is invalid, no TypeError -------------------------

    public function test_array_value_is_invalid_no_type_error(): void
    {
        // FPTP-19: a non-scalar (array) stored answer is invalid, must not crash.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Ana', ['Ana', 'Betty']]);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(1, $result['valid_votes']);
        assertEquals(1, $result['invalid']);
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
    }

    // ---- D9/D10: all-invalid / all-abstain give no winner -----------------

    public function test_all_invalid_gives_no_winner(): void
    {
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['Zelda', '', ['x']]);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(0, $result['valid_votes']);
        assertEquals(3, $result['invalid']);
        assertEquals(null, $result['winner']);
        assertEquals([], $result['winners']);
        // full roster still rendered at 0
        assertEquals([
            'Ana' => 0,
            'Betty' => 0,
            'Charles' => 0,
            'David' => 0,
            'Ernest' => 0,
        ], $result['state']);
    }

    public function test_all_abstain_gives_no_winner(): void
    {
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, ['abstain', 'abstain']);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals(0, $result['valid_votes']);
        assertEquals(2, $result['abstentions']);
        assertEquals(0, $result['invalid']);
        assertEquals(2, $result['total_votes']);
        assertEquals(null, $result['winner']);
        assertEquals([], $result['winners']);
    }

    // ---- Validator: abstainable branch appends 'abstain' to Rule::in -------

    public function test_get_submissions_validator_appends_abstain_when_abstainable(): void
    {
        // The abstainable branch of getSubmissionValidator: 'abstain' is appended
        // to the allowed set so the form's Abstain radio submits a valid value.
        $election = Election::factory()->abstainable()->make();
        $component = $this->fptpComponent();

        $validator = FirstPastThePost::getSubmissionValidator($component, $election);

        assertEquals([
            $component->id => [
                'required',
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest', 'abstain']),
            ],
        ], $validator);
    }

    // ---- Mixed buckets in one tally: real + abstain + invalid -------------

    public function test_mixed_real_abstain_and_invalid_buckets(): void
    {
        // Ana x3, Betty x1 (real) + abstain x2 + invalid x2 ('Zelda' out-of-options,
        // '' empty-string) on an abstainable election. Each lands in its own bucket;
        // the real-option leader still wins and total reconciles.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Ana', 'Betty',
            'abstain', 'abstain',
            'Zelda', '',
        ]);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        assertEquals(4, $result['valid_votes']);
        assertEquals(2, $result['abstentions']);
        assertEquals(2, $result['invalid']);
        assertEquals(8, $result['total_votes']);
        // total_votes == valid_votes + abstentions + invalid
        assertEquals(
            $result['valid_votes'] + $result['abstentions'] + $result['invalid'],
            $result['total_votes'],
        );
        assertEquals('Ana', $result['winner']);
        assertEquals(['Ana'], $result['winners']);
    }

    // ---- Order independence (anonymity / neutrality) ----------------------

    public function test_outcome_is_order_independent(): void
    {
        // Same multiset of votes, two different orders, and options declared in two
        // different orders -> identical state (by value), valid_votes, and winner.
        $optionsA = ['Ana', 'Betty', 'Charles', 'David', 'Ernest'];
        $optionsB = ['Ernest', 'David', 'Charles', 'Betty', 'Ana'];

        $componentA = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => $optionsA,
        ]);
        $componentB = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => $optionsB,
        ]);

        $order1 = ['Ana', 'Ana', 'Ana', 'Betty', 'Betty', 'Charles'];
        $order2 = ['Charles', 'Betty', 'Ana', 'Betty', 'Ana', 'Ana'];

        $resultA = FirstPastThePost::calculateResults(
            $this->votesFor($componentA, $order1),
            $componentA,
        );
        $resultB = FirstPastThePost::calculateResults(
            $this->votesFor($componentB, $order2),
            $componentB,
        );

        // Counts per option agree regardless of vote order and options order.
        assertEquals(
            ['Ana' => 3, 'Betty' => 2, 'Charles' => 1, 'David' => 0, 'Ernest' => 0],
            $resultA['state'],
        );
        assertEquals(
            ['Ana' => 3, 'Betty' => 2, 'Charles' => 1, 'David' => 0, 'Ernest' => 0],
            $resultB['state'],
        );
        assertEquals($resultA['valid_votes'], $resultB['valid_votes']);
        assertEquals('Ana', $resultA['winner']);
        assertEquals('Ana', $resultB['winner']);
        assertEquals($resultA['winner'], $resultB['winner']);
        assertEquals($resultA['winners'], $resultB['winners']);
    }

    // ---- Monotonicity: padding the sole leader keeps it winning -----------

    public function test_monotonicity_extra_vote_for_leader_keeps_winner(): void
    {
        // FPTP-05: Ana already leads (Ana 2, Betty 1). Adding one more Ana vote must
        // not change the winner — a sole leader stays the sole leader.
        $component = $this->fptpComponent();

        $before = FirstPastThePost::calculateResults(
            $this->votesFor($component, ['Ana', 'Ana', 'Betty']),
            $component,
        );
        $after = FirstPastThePost::calculateResults(
            $this->votesFor($component, ['Ana', 'Ana', 'Betty', 'Ana']),
            $component,
        );

        assertEquals('Ana', $before['winner']);
        assertEquals('Ana', $after['winner']);
        assertEquals(['Ana'], $after['winners']);
        assertEquals(3, $after['state']['Ana']);
        assertEquals(1, $after['state']['Betty']);
    }

    // ---- validateOptions: per-option rules --------------------------------

    public function test_validate_options_rules(): void
    {
        // min:2 (array), distinct, non-empty (min:1), and array-typed.
        assertEquals(true, FirstPastThePost::validateOptions(['A', 'B']));
        assertEquals(false, FirstPastThePost::validateOptions(['A']));        // min:2
        assertEquals(false, FirstPastThePost::validateOptions(['A', 'A']));   // distinct
        assertEquals(false, FirstPastThePost::validateOptions(['A', '']));    // non-empty
        assertEquals(false, FirstPastThePost::validateOptions('A'));          // non-array
    }

    // ---- CSV export: base-class default, no abstain coalescing ------------

    public function test_values_to_csv_returns_raw_value(): void
    {
        // FPTP does not override valuesToCsv, so the base class returns the stored
        // value verbatim — a real option and the literal 'abstain' both pass through.
        $component = $this->fptpComponent();
        $id = $component->id;

        assertEquals('Ana', FirstPastThePost::valuesToCsv([$id => 'Ana'], $id));
        assertEquals('abstain', FirstPastThePost::valuesToCsv([$id => 'abstain'], $id));
    }

    // ---- D1: real-option shares sum to ~100% of valid votes ---------------

    public function test_real_option_percentages_sum_to_one_hundred(): void
    {
        // Several real-option votes alongside abstentions and invalid values.
        // The per-option shares are computed exactly as the result view does
        // (round(state[opt] / valid_votes * 100, 2)); they must sum to ~100,
        // proving the percentage denominator is valid_votes (D1) and that
        // abstentions/invalid are excluded from it end-to-end.
        $component = $this->fptpComponent();
        $votes = $this->votesFor($component, [
            'Ana', 'Ana', 'Ana',
            'Betty', 'Betty',
            'Charles',
            'abstain', 'abstain',           // excluded from denominator
            'Zelda', '', ['x'],             // invalid, excluded from denominator
        ]);

        $result = FirstPastThePost::calculateResults($votes, $component, true);

        $validVotes = $result['valid_votes'];
        assertEquals(6, $validVotes);
        assertEquals(2, $result['abstentions']);
        assertEquals(3, $result['invalid']);

        $sum = 0.0;
        foreach ($result['state'] as $count) {
            $sum += round(($count / $validVotes) * 100, 2);
        }

        // Allow a small rounding epsilon — the per-option round() can leave a
        // residue away from an exact 100 (here 50 + 33.33 + 16.67 = 100.00).
        $this->assertTrue(
            $validVotes > 0 && abs($sum - 100) < 0.05,
            "Real-option shares must sum to ~100% of valid votes, got {$sum}",
        );
    }

    // ---- 'tie'-sentinel collision: KNOWN low-severity behaviour ------------

    public function test_option_literally_named_tie_winning_collides_with_tie_sentinel(): void
    {
        // KNOWN, LOW-SEVERITY SENTINEL COLLISION (pinned, not fixed here):
        // 'tie' is also the sentinel returned in $result['winner'] for a real
        // multi-way tie. An option literally named 'tie' that wins OUTRIGHT
        // therefore yields winner === 'tie' / winners === ['tie'], which is
        // indistinguishable from a genuine multi-way tie. A non-collidable
        // sentinel is a deferred broader change; do NOT alter the sentinel
        // mechanism in the component. This test pins the CURRENT behaviour so
        // any future sentinel change deliberately updates it.
        $component = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['tie', 'B'],
        ]);
        $votes = $this->votesFor($component, ['tie', 'tie', 'B']);

        $result = FirstPastThePost::calculateResults($votes, $component);

        assertEquals(['tie' => 2, 'B' => 1], $result['state']);
        assertEquals(3, $result['valid_votes']);
        // 'tie' won outright (2 vs 1), yet winner collides with the tie sentinel.
        assertEquals('tie', $result['winner']);
        assertEquals(['tie'], $result['winners']);
    }
}
