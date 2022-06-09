<?php

namespace App\BallotComponents\RankedChoice\v1;

use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class RankedChoiceTest extends TestCase
{
    public function test_get_submissions_validator()
    {
        $election = Election::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'RankedChoice',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
        ]);

        $validator = RankedChoice::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => [
                'required',
            ],
            "$component->id.*" => [
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
            ],
        ], $validator);
    }

    public function test_calculate_results()
    {
        $ballot = Ballot::factory()->make();
        $component = BallotComponent::factory()->make([
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
                    $component->id => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
                ]],
                ['values' => [
                    $component->id => ["Charles", "Betty", "Ernest", "Ana", "David"]
                ]],
                ['values' => [
                    $component->id => ["Ernest", "Betty", "David", "Charles", "Ana"]
                ]],
                ['values' => [
                    $component->id => ["Ana", "Betty", "David", "Charles", "Ernest"]
                ]],
                ['values' => [
                    $component->id => ["Ernest", "Betty", "David", "Charles", "Ana"]
                ]],
                ['values' => [
                    $component->id => ["Charles", "Ana", "David", "Betty", "Ernest"]
                ]],
                ['values' => [
                    $component->id => ["Betty", "Ana", "David", "Charles", "Ernest"]
                ]],
                ['values' => [
                    $component->id => ["Ana", "Charles", "David", "Ernest", "Betty"]
                ]]
            )->make();

        $results = RankedChoice::calculateResults($votes->values()->all(), $component);
        assertEquals($results['result']['winners'], ['Ana', 'tie']);
    }
}
