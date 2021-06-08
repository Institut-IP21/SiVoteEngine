<?php

namespace App\BallotComponents\YesNo\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class YesNoTest extends TestCase
{
    public function test_get_submissions_validator()
    {
        $election = Election::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'options' => ['yes', 'no']
        ]);
        $validator = YesNo::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => [
                'required',
                Rule::in(['yes', 'no'])
            ]
        ], $validator);
    }

    public function test_calculate_results()
    {
        $ballot = Ballot::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'options' => ['yes', 'no'],
            'ballot' => $ballot
        ]);

        $votes = Vote::factory()
            ->count(30)
            ->state([
                'ballot_id' => $ballot->id,
            ])->sequence(
                function () use ($component) {
                    return [
                        'values' => [
                            $component->id => Arr::random(['yes', 'no'])
                        ]
                    ];
                },
            )->make();

        $countYes = $votes->filter(function ($vote) use ($component) {
            return $vote->values[$component->id] === 'yes';
        })->count();
        $countNo = 30 - $countYes;
        $results = YesNo::calculateResults($votes->values()->all(), $component);

        assertEquals([ 'yes' => $countYes, 'no' => $countNo ], $results);
    }
}
