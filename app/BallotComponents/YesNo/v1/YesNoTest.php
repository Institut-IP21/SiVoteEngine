<?php

declare(strict_types=1);

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
    private YesNo $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new YesNo();
    }

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'options' => ['yes', 'no']
        ]);

        $validator = $this->component->getSubmissionValidator($ballotComponent, $election);

        assertEquals([
            $ballotComponent->id => [
                'required',
                Rule::in(['yes', 'no'])
            ]
        ], $validator->toArray());
    }

    public function test_calculate_results(): void
    {
        $ballot = Ballot::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'YesNo',
            'options' => ['yes', 'no'],
            'ballot' => $ballot
        ]);

        $votes = Vote::factory()
            ->count(30)
            ->state([
                'ballot_id' => $ballot->id,
            ])->sequence(
                function () use ($ballotComponent) {
                    return [
                        'values' => [
                            $ballotComponent->id => Arr::random(['yes', 'no'])
                        ]
                    ];
                },
            )->make();

        $countYes = $votes->filter(function ($vote) use ($ballotComponent) {
            return $vote->values[$ballotComponent->id] === 'yes';
        })->count();
        $countNo = 30 - $countYes;

        $results = $this->component->calculateResults(collect($votes), $ballotComponent);

        assertEquals(['yes' => $countYes, 'no' => $countNo], $results->toArray()['state']);
    }
}
