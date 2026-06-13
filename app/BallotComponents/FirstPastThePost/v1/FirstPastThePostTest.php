<?php

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
    public function test_get_submissions_validator(): void
    {
        $election = Election::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest']
        ]);
        $validator = FirstPastThePost::getSubmissionValidator($component, $election);
        assertEquals([
            $component->id => [
                'required',
                Rule::in(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
            ]
        ], $validator);
    }

    public function test_calculate_results(): void
    {
        $ballot = Ballot::factory()->make();
        $component = BallotComponent::factory()->make([
            'type' => 'FirstPastThePost',
            'options' => ['Ana', 'Betty', 'Charles', 'David', 'Ernest'],
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
                            $component->id => Arr::random(['Ana', 'Betty', 'Charles', 'David', 'Ernest'])
                        ]
                    ];
                },
            )->make();

        $groups = $votes->groupBy(function (Vote $vote) use ($component): string {
            return (string) (($vote->values ?? [])[$component->id] ?? '');
        });
        $results = FirstPastThePost::calculateResults($votes->values()->all(), $component);

        assertEquals([
            'Ana' => ($groups['Ana'] ?? collect())->count(),
            'Betty' => ($groups['Betty'] ?? collect())->count(),
            'Charles' => ($groups['Charles'] ?? collect())->count(),
            'David' => ($groups['David'] ?? collect())->count(),
            'Ernest' => ($groups['Ernest'] ?? collect())->count()
        ], $results['state']);
    }
}
