<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Orchestration tests for the OrderedList component: wires the pure
 * Ranked-Pairs calculation classes (Tasks 1-6) over parsed/accounted
 * ballots and asserts the resulting OrderedListResult shape.
 */
class OrderedListTest extends TestCase
{
    private OrderedList $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new OrderedList();
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed> $settings
     */
    private function makeComponent(array $options, array $settings = []): BallotComponent
    {
        return BallotComponent::factory()->make([
            'type' => 'OrderedList',
            'options' => $options,
            'settings' => $settings,
            'ballot_id' => (string) Str::uuid(),
        ]);
    }

    /**
     * @param list<list<string>> $rankings
     * @return array<int, Vote>
     */
    private function votes(BallotComponent $component, array $rankings): array
    {
        $votes = [];
        foreach ($rankings as $ranking) {
            $votes[] = Vote::factory()->make([
                'ballot_id' => 'ballot-x',
                'values' => [$component->id => $ranking],
            ]);
        }
        return $votes;
    }

    /**
     * @param array<int, Vote> $votes
     * @return array<string, mixed>
     */
    private function calc(array $votes, BallotComponent $component): array
    {
        return $this->component->calculateResults(new Collection($votes), $component)->toArray();
    }

    public function test_get_submission_validator_matches_ranked_choice_shape(): void
    {
        $election = Election::factory()->make();
        $component = $this->makeComponent(['Ana', 'Betty', 'Charles']);

        $validator = $this->component->getSubmissionValidator($component, $election)->toArray();

        $this->assertEquals([
            $component->id => ['required', 'array'],
            "$component->id.*" => ['distinct', Rule::in(['Ana', 'Betty', 'Charles'])],
        ], $validator);
    }

    public function test_clean_chain_elects_top_seats_with_no_cutoff(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], ['seats' => 2]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame(['A', 'B'], $r['elected']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame(5, $r['accounting']['counted']);
    }

    public function test_genuine_tie_at_the_seat_boundary_surfaces_a_cutoff_decision(): void
    {
        $c = $this->makeComponent(['A', 'B'], ['seats' => 1]);
        $votes = $this->votes($c, [['A'], ['B']]);

        $r = $this->calc($votes, $c);

        $this->assertNotNull($r['cutoff_decision']);
        $this->assertSame([], $r['elected']);
    }

    public function test_binding_min_quota_promotes_and_marks_official_corrected(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => true],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame('corrected', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C'], $r['corrected']['order']);
        $this->assertFalse($r['corrected']['infeasible']);
        $this->assertFalse($r['corrected']['provisional']);
    }

    public function test_advisory_non_binding_quota_leaves_official_natural(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C', 'D'], [
            'seats' => 2,
            'categories' => ['A' => 'Eng', 'B' => 'Eng', 'C' => 'Sales', 'D' => 'Eng'],
            'quota' => ['category' => 'Sales', 'type' => 'min', 'count' => 1, 'binding' => false],
        ]);
        $votes = $this->votes($c, array_fill(0, 5, ['A', 'B', 'C', 'D']));

        $r = $this->calc($votes, $c);

        $this->assertSame('natural', $r['official']);
        $this->assertNotNull($r['corrected']);
        $this->assertSame(['A', 'C'], $r['corrected']['order']);
    }

    public function test_empty_votes_returns_fully_formed_empty_shape(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);

        $r = $this->calc([], $c);

        $this->assertSame(0, $r['accounting']['cast']);
        $this->assertSame(0, $r['accounting']['blank']);
        $this->assertSame(0, $r['accounting']['invalid_only']);
        $this->assertSame(0, $r['accounting']['counted']);
        $this->assertSame([], $r['ranking']);
        $this->assertSame([], $r['elected']);
        $this->assertSame([], $r['bands']);
        $this->assertNull($r['cutoff_decision']);
        $this->assertSame([], $r['resolutions']);
        $this->assertNull($r['final']);
        $this->assertNull($r['corrected']);
        $this->assertSame('natural', $r['official']);
        $this->assertSame([], $r['lock_in_log']);
        $this->assertSame(['A', 'B', 'C'], $r['pairwise']['candidates']);
        $this->assertSame([], $r['pairwise']['matrix']);
    }

    public function test_accounting_reconciles_blank_invalid_and_duplicate_ballots(): void
    {
        $c = $this->makeComponent(['A', 'B', 'C']);

        $votes = [
            // unanswered
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => null]),
            // blank: empty ranking
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => []]]),
            // blank: a scalar rather than a list
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => 'A']]),
            // invalid_only: single out-of-roster label
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z']]]),
            // invalid_only: only out-of-roster labels
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['Z', 'Q']]]),
            // counted: duplicate label collapses to one entry
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['A', 'A']]]),
            // counted: normal ranking
            Vote::factory()->make(['ballot_id' => 'ballot-x', 'values' => [$c->id => ['A', 'B']]]),
        ];

        $r = $this->calc($votes, $c);
        $acc = $r['accounting'];

        $this->assertSame(7, $acc['cast']);
        $this->assertSame(3, $acc['blank']);
        $this->assertSame(2, $acc['invalid_only']);
        $this->assertSame(2, $acc['counted']);
        $this->assertSame($acc['cast'], $acc['blank'] + $acc['invalid_only'] + $acc['counted']);
    }

    public function test_values_to_csv_joins_ranked_labels_in_order(): void
    {
        $this->assertSame('B, A', $this->component->valuesToCsv(['cid' => ['B', 'A']], 'cid'));
        $this->assertSame('', $this->component->valuesToCsv([], 'cid'));
    }
}
