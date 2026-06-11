<?php

namespace App\Services;

use App\BallotComponents\ApprovalVote\v1\ApprovalVote;
use App\BallotComponents\BallotComponentType;
use App\BallotComponents\FirstPastThePost\v1\FirstPastThePost;
use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\BallotComponents\YesNo\v1\YesNo;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Vote;
use League\Csv\Writer;

class BallotService
{
    /** @var array<string, array<string, class-string<BallotComponentType>>> */
    protected $components = [
        'YesNo' => [
            'v1' => YesNo::class
        ],
        'FirstPastThePost' => [
            'v1' => FirstPastThePost::class
        ],
        'RankedChoice' => [
            'v1' => RankedChoice::class
        ],
        'ApprovalVote' => [
            'v1' => ApprovalVote::class
        ]
    ];

    /** @return array<string, array<string, array<string, mixed>>> */
    public function getComponentTree(): array
    {
        $tree = [];
        foreach ($this->components as $type => $versions) {
            $tree[$type] = [];
            foreach ($versions as $version => $class) {
                $tree[$type][$version] = [
                    'needsOptions' => $class::$needsOptions,
                    'livewireForm' => $class::$livewireForm,
                    'optionsValidators' => $class::$optionsValidator,
                    'strings' => $class::strings()
                ];
            }
        }
        return $tree;
    }

    /** @return array<int, string> */
    public function getBallotTypes(): array
    {
        return array_keys($this->components);
    }

    /** @return array<int, string> */
    public function getBallotVersions(string $ballotType): array
    {
        if (!array_key_exists($ballotType, $this->components)) {
            return [];
        }
        return array_keys($this->components[$ballotType]);
    }

    /** @return array<string, mixed> */
    public function getSubmissionValidators(Ballot $ballot): array
    {
        return array_reduce($ballot->components, function ($validators, $component) use ($ballot) {
            return array_merge($validators, $this->components[$component['type']][$component['version']]::getSubmissionValidator($component, $ballot->election));
        }, []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getPartialSubmissionValidators(Ballot $ballot, array $params): array
    {
        return array_reduce($ballot->components, function ($validators, $component) use ($ballot, $params) {
            if (!array_key_exists($component->id, $params)) {
                return $validators;
            }
            return array_merge($validators, $this->components[$component['type']][$component['version']]::getSubmissionValidator($component, $ballot->election));
        }, []);
    }

    /** @return array<string, mixed> */
    public function getComponentValidators(BallotComponent $component): array
    {
        return $this->components[$component->type][$component->version]::getSubmissionValidator($component, $component->ballot->election);
    }

    /** @return class-string<BallotComponentType> */
    public function getBallotComponentClass(string $ballotType, string $version): string
    {
        return $this->components[$ballotType][$version];
    }

    /**
     * This returns an instance, but most classes are completely static, so it's currently unused.
     *
     * @param mixed $args
     */
    public function getBallotComponentClassInstance(string $ballotType, string $version, $args): BallotComponentType
    {
        $class = $this->getBallotComponentClass($ballotType, $version);
        /** @var BallotComponentType $instance */
        $instance = new $class($args);
        return $instance;
    }

    /** @return array<string, array<string, mixed>> */
    public function calculateResults(Ballot $ballot): array
    {
        $votes = $ballot->cast_votes;
        /** @var array<string, array<string, mixed>> $result */
        $result = $ballot->components()->get()->reduce(function ($acc, BallotComponent $component) use ($votes) {
            $componentClass = $this->getBallotComponentClassInstance($component['type'], $component['version'], $component['settings']);
            $acc[$component->id] = [
                'results' => $componentClass::calculateResults($votes, $component),
                'title' => $component->title,
                'description' => $component->description,
                'type' => $component->type
            ];
            return $acc;
        }, []);
        return $result;
    }

    public function resultsCsv(Ballot $ballot): string
    {
        $votes = $ballot->castVotes();
        $components = $ballot->components()->get();

        $header = $components->pluck('title')->prepend(__('ballot.voteId'))->toArray();

        $results_per_component = $components->map(function (BallotComponent $component) use ($votes) {
            $componentClass = $this->getBallotComponentClassInstance($component['type'], $component['version'], $component['settings']);
            return $votes->map(function (Vote $vote) use ($componentClass, $component) {
                return $componentClass::valuesToCsv($vote->values, $component->id);
            });
        });

        $final_values = $votes->pluck('id')->zip(...$results_per_component);

        $csv = Writer::createFromString();

        $csv->insertOne($header);
        $csv->insertAll($final_values->toArray());

        return $csv->getContent();
    }
}
