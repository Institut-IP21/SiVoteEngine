<?php

declare(strict_types=1);

namespace App\Services;

use App\BallotComponents\Contracts\BallotComponentInterface;
use App\BallotComponents\Support\ComponentRegistry;
use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use League\Csv\Writer;

/**
 * Service for ballot operations including result calculation and CSV export.
 */
final readonly class BallotService
{
    public function __construct(
        private ComponentRegistry $registry,
    ) {}

    /**
     * Get the component tree with metadata for all registered components.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getComponentTree(): array
    {
        $tree = [];

        foreach ($this->registry->all() as $type => $versions) {
            $tree[$type] = [];
            foreach (array_keys($versions) as $version) {
                $component = $this->registry->resolve($type, $version);
                $tree[$type][$version] = $component->getMetadata()->toArray();
            }
        }

        return $tree;
    }

    /**
     * Get all available ballot types.
     *
     * @return array<string>
     */
    public function getBallotTypes(): array
    {
        return $this->registry->getTypes();
    }

    /**
     * Get available versions for a ballot type.
     *
     * @return array<string>
     */
    public function getBallotVersions(string $ballotType): array
    {
        return $this->registry->getVersions($ballotType);
    }

    /**
     * Get submission validators for all components in a ballot.
     *
     * @return array<string, array<mixed>>
     */
    public function getSubmissionValidators(Ballot $ballot): array
    {
        $validators = [];

        /** @var Election $election */
        $election = $ballot->election;

        foreach ($ballot->components as $componentModel) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $rules = $component->getSubmissionValidator($componentModel, $election);
            $validators = array_merge($validators, $rules->toArray());
        }

        return $validators;
    }

    /**
     * Get submission validators for only the submitted components.
     *
     * @param array<string, mixed> $params
     * @return array<string, array<mixed>>
     */
    public function getPartialSubmissionValidators(Ballot $ballot, array $params): array
    {
        $validators = [];

        /** @var Election $election */
        $election = $ballot->election;

        foreach ($ballot->components as $componentModel) {
            if (!array_key_exists($componentModel->id, $params)) {
                continue;
            }

            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $rules = $component->getSubmissionValidator($componentModel, $election);
            $validators = array_merge($validators, $rules->toArray());
        }

        return $validators;
    }

    /**
     * Get validators for a single component.
     *
     * @return array<string, array<mixed>>
     */
    public function getComponentValidators(BallotComponent $componentModel): array
    {
        $component = $this->registry->resolve($componentModel->type, $componentModel->version);
        /** @var Ballot $ballot */
        $ballot = $componentModel->ballot;
        /** @var Election $election */
        $election = $ballot->election;
        return $component->getSubmissionValidator($componentModel, $election)->toArray();
    }

    /**
     * Resolve a ballot component instance.
     */
    public function resolveComponent(string $type, string $version): BallotComponentInterface
    {
        return $this->registry->resolve($type, $version);
    }

    /**
     * Calculate results for all components in a ballot.
     *
     * @return array<string, array<string, mixed>>
     */
    public function calculateResults(Ballot $ballot): array
    {
        $votes = collect($ballot->cast_votes);
        $abstainable = (bool) ($ballot->election->abstainable ?? false);
        $results = [];

        // D11: delegate quorum to the Ballot accessor (our canonical semantics).
        $results['_meta'] = [
            'quorum' => $ballot->quorum,
            'votes_cast' => $ballot->votes_count,
            'quorum_met' => $ballot->quorum_met,
        ];

        foreach ($ballot->components()->get() as $componentModel) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);
            $result = $component->calculateResults($votes, $componentModel, $abstainable);

            $results[$componentModel->id] = [
                'results' => $result->toArray(),
                'title' => $componentModel->title,
                'description' => $componentModel->description,
                'type' => $componentModel->type,
            ];
        }

        return $results;
    }

    /**
     * Export ballot results to CSV format.
     */
    public function resultsCsv(Ballot $ballot): string
    {
        $votes = $ballot->castVotes();
        $components = $ballot->components()->get();

        $header = $components->pluck('title')->prepend(__('ballot.voteId'))->toArray();

        $resultsPerComponent = $components->map(function (BallotComponent $componentModel) use ($votes) {
            $component = $this->registry->resolve($componentModel->type, $componentModel->version);

            return $votes->map(fn (Vote $vote): string =>
                $component->valuesToCsv($vote->values ?? [], $componentModel->id)
            );
        });

        $finalValues = $votes->pluck('id')->zip(...$resultsPerComponent);

        $csv = Writer::createFromString();
        $csv->insertOne($header);
        $csv->insertAll($finalValues->toArray());

        return $csv->getContent();
    }
}
