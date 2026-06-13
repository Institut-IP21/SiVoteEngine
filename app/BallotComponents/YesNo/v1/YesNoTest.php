<?php

namespace App\BallotComponents\YesNo\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertNotContains;
use function PHPUnit\Framework\assertNotSame;
use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

class YesNoTest extends TestCase
{
    /**
     * Build a YesNo component, optionally carrying a `settings` payload
     * (Phase-1 stopgap home of `pass_threshold`).
     *
     * @param array<string, mixed>|null $settings
     */
    private function makeComponent(?array $settings = null): BallotComponent
    {
        $component = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'options' => ['yes', 'no'],
        ]);
        if ($settings !== null) {
            // `settings` is the Phase-1 stopgap home of `pass_threshold`; it is
            // not yet a model column, so set it as a runtime attribute.
            $component->setAttribute('settings', $settings);
        }
        return $component;
    }

    /**
     * Build a list of votes whose answer for $component is each of $answers
     * (one vote per entry). A null entry models a ballot that did not answer
     * this component at all (its values map omits the component id).
     *
     * @param array<int, string|null> $answers
     * @return array<int, Vote>
     */
    private function votes(BallotComponent $component, array $answers): array
    {
        return array_map(function (?string $answer) use ($component): Vote {
            $values = $answer === null ? [] : [$component->id => $answer];
            return Vote::factory()->make(['values' => $values]);
        }, $answers);
    }

    /**
     * @return array<int, string>
     */
    private function fill(BallotComponent $component, string $answer, int $n): array
    {
        return array_fill(0, $n, $answer);
    }

    // ---- validator (existing behaviour, retained) ----

    public function test_get_submissions_validator_non_abstainable(): void
    {
        $election = Election::factory()->make(['abstainable' => false]);
        $component = $this->makeComponent();
        $validator = YesNo::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => ['required', Rule::in(['yes', 'no'])],
        ], $validator);
    }

    public function test_get_submissions_validator_abstainable_includes_abstain(): void
    {
        $election = Election::factory()->make(['abstainable' => true]);
        $component = $this->makeComponent();
        $validator = YesNo::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => ['required', Rule::in(['yes', 'no', 'abstain'])],
        ], $validator);
    }

    public function test_validate_options_allow_list(): void
    {
        assertTrue(YesNo::validateOptions('yes'));
        assertTrue(YesNo::validateOptions('no'));
        assertFalse(YesNo::validateOptions('abstain'));
        assertFalse(YesNo::validateOptions('maybe'));
    }

    // ---- D10 full roster: both seeded at 0 ----

    public function test_empty_votes_full_roster_no_winner_not_carried(): void
    {
        $component = $this->makeComponent();
        $r = YesNo::calculateResults([], $component);

        assertSame(['yes' => 0, 'no' => 0], $r['state']);
        assertSame(0, $r['valid_votes']);
        assertSame(0, $r['abstentions']);
        assertSame(0, $r['invalid']);
        assertSame(0, $r['total_votes']);
        assertNull($r['winner']);
        assertSame([], $r['winners']);
        assertFalse($r['passed']);
    }

    public function test_unanimous_yes_shows_no_at_zero(): void
    {
        $component = $this->makeComponent();
        $r = YesNo::calculateResults($this->votes($component, $this->fill($component, 'yes', 5)), $component);

        assertSame(['yes' => 5, 'no' => 0], $r['state']);
        assertSame(5, $r['valid_votes']);
        assertSame(5, $r['total_votes']);
        assertSame('yes', $r['winner']);
        assertSame(['yes'], $r['winners']);
        assertTrue($r['passed']);
    }

    // ---- D5 motion outcome: clear yes / clear no ----

    public function test_clear_yes_passes_default_majority(): void
    {
        $component = $this->makeComponent();
        // 6 yes / 5 no -> majority, > floor
        $answers = array_merge($this->fill($component, 'yes', 6), $this->fill($component, 'no', 5));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(['yes' => 6, 'no' => 5], $r['state']);
        assertSame(11, $r['valid_votes']);
        assertSame('yes', $r['winner']);
        assertSame(['yes'], $r['winners']);
        assertTrue($r['passed']);
        assertSame(50, $r['pass_threshold']);
    }

    public function test_clear_no_does_not_pass(): void
    {
        $component = $this->makeComponent();
        // 5 yes / 6 no
        $answers = array_merge($this->fill($component, 'yes', 5), $this->fill($component, 'no', 6));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(['yes' => 5, 'no' => 6], $r['state']);
        assertSame(11, $r['valid_votes']);
        assertSame('no', $r['winner']);
        assertSame(['no'], $r['winners']);
        assertFalse($r['passed']);
    }

    // ---- D5 tie = not carried ----

    public function test_exact_tie_not_carried(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge($this->fill($component, 'yes', 5), $this->fill($component, 'no', 5));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(['yes' => 5, 'no' => 5], $r['state']);
        assertSame(10, $r['valid_votes']);
        assertSame('tie', $r['winner']);
        assertFalse($r['passed']);
    }

    // ---- D5 numeric supermajority threshold (70%) ----

    public function test_numeric_threshold_70_fails_at_65_percent(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 70]);
        // 13 yes / 7 no = 65%
        $answers = array_merge($this->fill($component, 'yes', 13), $this->fill($component, 'no', 7));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(20, $r['valid_votes']);
        assertSame('yes', $r['winner']);
        assertFalse($r['passed']);
        assertSame(70, $r['pass_threshold']);
    }

    public function test_numeric_threshold_70_passes_at_exactly_70_percent(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 70]);
        // 14 yes / 6 no = 70%, >= 70
        $answers = array_merge($this->fill($component, 'yes', 14), $this->fill($component, 'no', 6));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(20, $r['valid_votes']);
        assertSame('yes', $r['winner']);
        assertTrue($r['passed']);
    }

    // ---- D5 preset two_thirds: exact rational vs literal 66.67 caveat ----

    public function test_preset_two_thirds_exact_2_of_3_passes(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        // 2 yes / 1 no = 66.66..% -> exact 2/3 via yes*3 >= 2*(yes+no)
        $answers = array_merge($this->fill($component, 'yes', 2), $this->fill($component, 'no', 1));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(3, $r['valid_votes']);
        assertTrue($r['passed']);
        assertSame('two_thirds', $r['pass_threshold']);
    }

    public function test_preset_two_thirds_fails_at_60_percent(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 'two_thirds']);
        // 3 yes / 2 no = 60% -> below 2/3, but yes still leads.
        $answers = array_merge($this->fill($component, 'yes', 3), $this->fill($component, 'no', 2));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(5, $r['valid_votes']);
        assertSame('yes', $r['winner']);
        assertFalse($r['passed']);
        assertSame('two_thirds', $r['pass_threshold']);
    }

    // ---- unknown preset string silently falls back to simple majority ----

    public function test_unknown_preset_falls_back_to_simple_majority(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 'three_fifths']);
        // Unrecognised string -> treated as default 50, so 6 yes / 5 no carries.
        $answers = array_merge($this->fill($component, 'yes', 6), $this->fill($component, 'no', 5));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(11, $r['valid_votes']);
        assertSame('yes', $r['winner']);
        assertTrue($r['passed']);
        // The unknown string is echoed back verbatim even though it acts as 50.
        assertSame('three_fifths', $r['pass_threshold']);
    }

    // ---- production default: no settings -> threshold 50 echoed ----

    public function test_default_threshold_is_50_when_no_settings(): void
    {
        $component = $this->makeComponent();
        // 6 yes / 5 no carries under the default simple majority.
        $answers = array_merge($this->fill($component, 'yes', 6), $this->fill($component, 'no', 5));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(50, $r['pass_threshold']);
        assertSame(11, $r['valid_votes']);
        assertTrue($r['passed']);
    }

    public function test_literal_66_67_fails_exact_2_of_3(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 66.67]);
        // 2 yes / 1 no = 66.66..% which is < 66.67 typed literally -> fails
        $answers = array_merge($this->fill($component, 'yes', 2), $this->fill($component, 'no', 1));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(3, $r['valid_votes']);
        assertFalse($r['passed']);
        assertSame(66.67, $r['pass_threshold']);
    }

    public function test_preset_three_quarters_exact_3_of_4_passes(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 'three_quarters']);
        // 3 yes / 1 no = 75% -> exact 3/4 via yes*4 >= 3*(yes+no)
        $answers = array_merge($this->fill($component, 'yes', 3), $this->fill($component, 'no', 1));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(4, $r['valid_votes']);
        assertTrue($r['passed']);
    }

    // ---- D1 abstentions excluded from threshold denominator ----

    public function test_abstentions_excluded_from_threshold(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 70]);
        // 14 yes / 6 no (=70% of 20 valid) + 10 abstain
        $answers = array_merge(
            $this->fill($component, 'yes', 14),
            $this->fill($component, 'no', 6),
            $this->fill($component, 'abstain', 10),
        );
        // abstain only legitimate when abstainable
        $r = YesNo::calculateResults($this->votes($component, $answers), $component, true);

        assertSame(['yes' => 14, 'no' => 6], $r['state']);
        assertSame(20, $r['valid_votes']);
        assertSame(10, $r['abstentions']);
        assertSame(0, $r['invalid']);
        assertSame(30, $r['total_votes']);
        assertSame('yes', $r['winner']);
        assertTrue($r['passed']);
    }

    public function test_abstain_counted_separately_not_in_state(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge(
            $this->fill($component, 'yes', 4),
            $this->fill($component, 'no', 1),
            $this->fill($component, 'abstain', 2),
        );
        $r = YesNo::calculateResults($this->votes($component, $answers), $component, true);

        // D1/YN-14: shares over valid votes only -> yes = 4/5 = 80%
        assertSame(['yes' => 4, 'no' => 1], $r['state']);
        assertSame(5, $r['valid_votes']);
        assertSame(2, $r['abstentions']);
        assertSame(0, $r['invalid']);
        assertSame(7, $r['total_votes']);
        assertSame('yes', $r['winner']);
        assertTrue($r['passed']);
    }

    // ---- D9 missing answer: abstain when abstainable, invalid when not ----

    public function test_missing_answer_is_abstain_when_abstainable(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge($this->fill($component, 'yes', 3), [null, null]);
        $r = YesNo::calculateResults($this->votes($component, $answers), $component, true);

        assertSame(['yes' => 3, 'no' => 0], $r['state']);
        assertSame(3, $r['valid_votes']);
        assertSame(2, $r['abstentions']);
        assertSame(0, $r['invalid']);
        assertSame(5, $r['total_votes']);
    }

    public function test_missing_answer_is_invalid_when_not_abstainable(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge($this->fill($component, 'yes', 3), [null, null]);
        $r = YesNo::calculateResults($this->votes($component, $answers), $component, false);

        assertSame(['yes' => 3, 'no' => 0], $r['state']);
        assertSame(3, $r['valid_votes']);
        assertSame(0, $r['abstentions']);
        assertSame(2, $r['invalid']);
        assertSame(5, $r['total_votes']);
    }

    // ---- D9 invalid tokens: maybe / unknown / non-scalar -> invalid, never wins ----

    public function test_maybe_is_invalid_and_never_wins(): void
    {
        $component = $this->makeComponent();
        // 1 yes, 5 maybe (out-of-options)
        $answers = array_merge($this->fill($component, 'yes', 1), $this->fill($component, 'maybe', 5));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(['yes' => 1, 'no' => 0], $r['state']);
        assertSame(1, $r['valid_votes']);
        assertSame(5, $r['invalid']);
        assertSame(6, $r['total_votes']);
        assertSame('yes', $r['winner']);
        assertSame(['yes'], $r['winners']);
        assertNotContains('maybe', $r['winners']);
        assertTrue($r['passed']);
    }

    public function test_abstain_token_is_invalid_when_not_abstainable(): void
    {
        $component = $this->makeComponent();
        // 'abstain' stored but election is NOT abstainable -> invalid, not abstention
        $answers = array_merge($this->fill($component, 'yes', 3), $this->fill($component, 'abstain', 2));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component, false);

        assertSame(['yes' => 3, 'no' => 0], $r['state']);
        assertSame(3, $r['valid_votes']);
        assertSame(0, $r['abstentions']);
        assertSame(2, $r['invalid']);
    }

    public function test_non_scalar_answer_is_invalid_no_typeerror(): void
    {
        $component = $this->makeComponent();
        $vote = Vote::factory()->make(['values' => [$component->id => ['yes', 'no']]]);
        $good = $this->votes($component, $this->fill($component, 'yes', 2));
        $r = YesNo::calculateResults(array_merge($good, [$vote]), $component);

        assertSame(['yes' => 2, 'no' => 0], $r['state']);
        assertSame(2, $r['valid_votes']);
        assertSame(1, $r['invalid']);
        assertSame(3, $r['total_votes']);
    }

    // ---- D5 sub-50 threshold floor: never minority passage ----

    public function test_threshold_below_50_still_requires_majority(): void
    {
        $component = $this->makeComponent(['pass_threshold' => 40]);
        // 4 yes / 6 no -> minority, must NOT pass despite low threshold
        $answers = array_merge($this->fill($component, 'yes', 4), $this->fill($component, 'no', 6));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame('no', $r['winner']);
        assertFalse($r['passed']);
    }

    // ---- invalid never wins even when it is the plurality ----

    public function test_invalid_never_wins_even_as_plurality(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge(
            $this->fill($component, 'yes', 1),
            $this->fill($component, 'no', 1),
            $this->fill($component, 'maybe', 10),
        );
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        assertSame(2, $r['valid_votes']);
        assertSame(10, $r['invalid']);
        assertSame('tie', $r['winner']);
        assertFalse($r['passed']);
        assertNotContains('maybe', $r['winners']);
    }

    // ---- all-voters-abstain (abstainable): no decision, no division-by-zero ----

    public function test_all_voters_abstain_no_decision_no_div_by_zero(): void
    {
        $component = $this->makeComponent();
        // Only abstention ballots on an abstainable election. The calculator must
        // not divide by zero (valid_votes == 0) and must yield no decision.
        $r = YesNo::calculateResults($this->votes($component, $this->fill($component, 'abstain', 7)), $component, true);

        assertSame(['yes' => 0, 'no' => 0], $r['state']);
        assertSame(0, $r['valid_votes']);
        assertSame(7, $r['abstentions']);
        assertSame(0, $r['invalid']);
        assertSame(7, $r['total_votes']);
        assertNull($r['winner']);
        assertSame([], $r['winners']);
        assertFalse($r['passed']);
    }

    // ---- D5 tie: winners insertion order is exactly ['yes','no'] ----

    public function test_tie_winners_insertion_order_is_yes_then_no(): void
    {
        $component = $this->makeComponent();
        $answers = array_merge($this->fill($component, 'yes', 3), $this->fill($component, 'no', 3));
        $r = YesNo::calculateResults($this->votes($component, $answers), $component);

        // Order matters: yes is seeded/listed before no (D10 roster order).
        assertSame(['yes', 'no'], $r['winners']);
        assertSame('tie', $r['winner']);
        assertFalse($r['passed']);
    }

    // ---- D9 empty-string answer is invalid (non-abstainable), never winnable ----

    public function test_empty_string_answer_is_invalid_when_not_abstainable(): void
    {
        $component = $this->makeComponent();
        $empty = Vote::factory()->make(['values' => [$component->id => '']]);
        $good = $this->votes($component, $this->fill($component, 'yes', 2));
        $r = YesNo::calculateResults(array_merge($good, [$empty]), $component, false);

        assertSame(['yes' => 2, 'no' => 0], $r['state']);
        assertSame(2, $r['valid_votes']);
        assertSame(0, $r['abstentions']);
        assertSame(1, $r['invalid']);
        assertSame(3, $r['total_votes']);
        assertSame('yes', $r['winner']);
        assertSame(['yes'], $r['winners']);
        assertNotContains('', $r['winners']);
    }

    // ---- D5 lang keys resolve (the gap that hid the blade bug) ----

    public function test_d5_lang_keys_resolve(): void
    {
        app()->setLocale('en');

        assertNotSame('components.yesno.carried', trans('components.yesno.carried'));
        assertNotSame('components.yesno.not_carried', trans('components.yesno.not_carried'));
        assertNotSame('components.yesno.invalid', trans('components.yesno.invalid'));
        assertNotSame('components.yesno.not_carried_tied', trans('components.yesno.not_carried_tied'));
    }

    // ---- valuesToCsv: present key -> raw stored value verbatim; absent key -> '' ----

    public function test_values_to_csv_returns_raw_value_verbatim(): void
    {
        $component = $this->makeComponent();
        $id = $component->id;

        assertSame('yes', YesNo::valuesToCsv([$id => 'yes'], $id));
        assertSame('no', YesNo::valuesToCsv([$id => 'no'], $id));
        assertSame('abstain', YesNo::valuesToCsv([$id => 'abstain'], $id));
    }

    public function test_values_to_csv_returns_empty_string_when_key_absent(): void
    {
        $component = $this->makeComponent();
        $id = $component->id;

        assertSame('', YesNo::valuesToCsv([], $id));
    }
}
