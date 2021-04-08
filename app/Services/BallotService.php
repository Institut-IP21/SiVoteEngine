<?php

namespace App\Services;

use App\BallotComponents\BallotComponentType;
use App\BallotComponents\FirstPassThePost\v1\FirstPassThePost;
use App\BallotComponents\RankedChoice\v1\RankedChoice;
use App\BallotComponents\YesNo\v1\YesNo;
use App\Models\Ballot;
use App\Models\Vote;
use League\Csv\Writer;

class BallotService
{
    protected $components = [
        'YesNo' => [
            'v1' => YesNo::class
        ],
        'FirstPassThePost' => [
            'v1' => FirstPassThePost::class
        ],
        'RankedChoice' => [
            'v1' => RankedChoice::class
        ]
    ];

    public function getComponentTree()
    {
        $tree = [];
        foreach ($this->components as $type => $versions) {
            $tree[$type] = [];
            foreach ($versions as $version => $class) {
                $tree[$type][$version] = [
                    'needsOptions' => $class::$needsOptions,
                ];
            }
        }
        return $tree;
    }

    public function getBallotTypes()
    {
        return array_keys($this->components);
    }

    public function getBallotVersions($ballotType)
    {
        if (!array_key_exists($ballotType, $this->components)) {
            return [];
        }
        return array_keys($this->components[$ballotType]);
    }

    public function getSubmissionValidators(Ballot $ballot)
    {
        return array_reduce($ballot->components, function ($acc, $component) use ($ballot) {
            return array_merge($acc, $this->components[$component['type']][$component['version']]::getSubmissionValidator($component, $ballot->election));
        }, []);
    }

    public function getBallotComponentClass($ballotType, $version)
    {
        return $this->components[$ballotType][$version];
    }

    /**
     * This returns an instance, but most classes are completely static, so it's currently unused.
     */
    public function getBallotComponentClassInstance($ballotType, $version, $args): BallotComponentType
    {
        $class = $this->getBallotComponentClass($ballotType, $version);
        return new $class($args);
    }

    public function calculateResults(Ballot $ballot)
    {
        $votes = $ballot->cast_votes;
        return $ballot->components()->get()->reduce(function ($acc, $component) use ($votes) {
            $componentClass = $this->getBallotComponentClassInstance($component['type'], $component['version'], $component['settings']);
            $acc[$component->id] = [
                'results' => $componentClass::calculateResults($votes, $component)
            ];
            $acc[$component->id] = array_merge($acc[$component->id], [
                'title' => $component->title,
                'description' => $component->description,
                'type' => $component->type
            ]);
            return $acc;
        }, []);
    }

    public function resultsCsv(Ballot $ballot)
    {
        $votes = $ballot->castVotes();
        $components = $ballot->components()->get();

        $header = $components->pluck('title')->prepend(__('VOTE ID'))->toArray();

        $results_per_component = $components->map(function ($component) use ($votes) {
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
