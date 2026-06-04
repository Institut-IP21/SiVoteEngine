<?php

declare(strict_types=1);

namespace App\BallotComponents\FirstPastThePost\v1;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Tests\TestCase;

use function PHPUnit\Framework\assertEquals;

class FirstPastThePostTest extends TestCase
{
    private FirstPastThePost $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = new FirstPastThePost();
    }

    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
        ]);

        $validator = $this->component->getSubmissionValidator($ballotComponent, $election);

        assertEquals([
            $ballotComponent->id => [
                'required',
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
            ]
        ], $validator->toArray());
    }

    public function test_calculate_results(): void
    {
        $ballot = Ballot::factory()->make();
        $ballotComponent = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
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
                            $ballotComponent->id => Arr::random(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
                        ]
                    ];
                },
            )->make();

        $groups = $votes->groupBy(function ($vote) use ($ballotComponent) {
            return $vote->values[$ballotComponent->id];
        });

        $results = $this->component->calculateResults(collect($votes), $ballotComponent);

        assertEquals([
            'Ana' => $groups->get('Ana', collect())->count(),
            'Betty' => $groups->get('Betty', collect())->count(),
            'Charles' => $groups->get('Charles', collect())->count(),
            'David' => $groups->get('David', collect())->count(),
            'Ernest' => $groups->get('Ernest', collect())->count()
        ], $results->toArray()['state']);
    }
}
