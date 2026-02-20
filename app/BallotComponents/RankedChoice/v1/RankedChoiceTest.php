<?php

declare(strict_types=1);

namespace App\BallotComponents\RankedChoice\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class RankedChoiceTest extends TestCase
{
    private RankedChoice $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new RankedChoice();
    }

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
        ]);

        $validator = $this->component->getSubmissionValidator($ballotComponent, $election);

        assertEquals([
            $ballotComponent->id => [
                'required',
            ],
            "$ballotComponent->id.*" => [
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
            ],
        ], $validator->toArray());
    }

    public function test_calculate_results(): void
    {
        $ballot = Ballot::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
            'ballot' => $ballot
        ]);

        $votes = Vote::factory()
            ->count(8)
            ->state([
                'ballot_id' => $ballot->id,
            ])->sequence(
                ['values' => [
                    $ballotComponent->id => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
                ]],
                ['values' => [
                    $ballotComponent->id => ["Charles", "Betty", "Ernest", "Ana", "David"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Ernest", "Betty", "David", "Charles", "Ana"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Ana", "Betty", "David", "Charles", "Ernest"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Ernest", "Betty", "David", "Charles", "Ana"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Charles", "Ana", "David", "Betty", "Ernest"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Betty", "Ana", "David", "Charles", "Ernest"]
                ]],
                ['values' => [
                    $ballotComponent->id => ["Ana", "Charles", "David", "Ernest", "Betty"]
                ]]
            )->make();

        $results = $this->component->calculateResults(collect($votes), $ballotComponent);

        assertEquals(['Ana', 'tie'], $results->toArray()['result']['winners']);
    }
}
