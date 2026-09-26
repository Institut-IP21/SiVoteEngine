<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\BallotComponents\OrderedList\v1\PairwiseMatrix;
use App\BallotComponents\OrderedList\v1\RankedPairsLock;
use App\Models\BallotComponent;
use App\Services\BallotService;
use Illuminate\Console\Command;

/**
 * Records a post-close election-runner decision for an OrderedList tie band
 * (or contested seat cutoff) that the votes alone didn't settle. The engine
 * never guesses at a resolution: this command validates that the proposed
 * --order is (a) a permutation of exactly the surfaced cluster and (b) does
 * not contradict any pairwise fact the votes already locked in, before it is
 * appended to the component's `runner_resolutions` column.
 */
class BallotResolveTie extends Command
{
    /** @var string */
    protected $signature = 'ballot:resolve-tie
                            {component : The OrderedList ballot component ID}
                            {--cluster= : Comma-separated candidate labels of the tied cluster to resolve}
                            {--order= : Comma-separated candidate labels giving the resolved order}
                            {--comment= : A note describing how the tie was broken (e.g. a coin toss)}
                            {--by= : Identifier of the person/role recording the resolution}';

    /** @var string */
    protected $description = 'Record a validated post-close runner resolution for an OrderedList tie '
        . '(candidate labels containing commas are not supported via --cluster/--order)';

    public function __construct(
        private readonly BallotService $ballotService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $component = BallotComponent::find($this->argument('component'));
        if ($component === null) {
            $this->error('No such ballot component.');

            return 1;
        }

        if ($component->type !== 'OrderedList') {
            $this->error("Component {$component->id} is a {$component->type}, not an OrderedList.");

            return 1;
        }

        /** @var \App\Models\Ballot $ballot */
        $ballot = $component->ballot;

        // A runner resolution settles what the VOTES left undecided; it can
        // only be recorded once the ballot has actually closed and no more
        // votes can change the locked partial order it is resolving against.
        if (!$ballot->finished) {
            $this->error('This ballot has not finished yet — a runner resolution can only be recorded once the ballot is closed.');

            return 1;
        }

        $cluster = $this->parseLabelList((string) $this->option('cluster'));
        $order = $this->parseLabelList((string) $this->option('order'));
        $by = (string) ($this->option('by') ?? '');
        $comment = (string) ($this->option('comment') ?? '');

        if ($cluster === [] || $order === [] || $by === '') {
            $this->error('--cluster, --order and --by are all required.');

            return 1;
        }

        $results = $this->ballotService->calculateResults($ballot);
        $componentResult = $results[$component->id]['results'] ?? null;
        if (!is_array($componentResult) || !array_key_exists('bands', $componentResult)) {
            $this->error('Could not calculate an OrderedList result for this component.');

            return 1;
        }

        /** @var list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands */
        $bands = $componentResult['bands'];

        $matchedCluster = $this->findMatchingCluster($cluster, $bands);
        if ($matchedCluster === null) {
            $this->error('No current unresolved tie matches --cluster.');

            return 1;
        }

        $reachable = $this->reachableFromVotes($ballot, $component);

        if (!$this->isValidResolution($matchedCluster, $order, $reachable)) {
            $this->error('--order is not a permutation of the cluster, or contradicts a locked pairwise result.');

            return 1;
        }

        /** @var list<array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}> $resolutions */
        $resolutions = is_array($component->runner_resolutions) ? $component->runner_resolutions : [];
        $resolutions[] = [
            'cluster' => $matchedCluster,
            'order' => $order,
            'comment' => $comment,
            'resolved_by' => $by,
            'resolved_at' => now()->toIso8601String(),
        ];

        $component->runner_resolutions = $resolutions;
        $component->save();

        $this->info(sprintf('Recorded resolution for [%s] as [%s] on component %s.', implode(', ', $matchedCluster), implode(', ', $order), $component->id));

        return 0;
    }

    /**
     * @return list<string>
     */
    private function parseLabelList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $labels = [];
        foreach (explode(',', $raw) as $label) {
            $label = trim($label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Find the surfaced band whose candidate SET equals the requested
     * --cluster, exactly as RunnerResolutionApplier matches a stored
     * resolution to a cluster. Resolvable clusters are the bands ONLY: the
     * cutoff decision is a display/summary of the contested subset (see
     * PositionResolver), not a separate resolvable unit -- a resolution
     * always has to fully order the band it belongs to, even when that band
     * also chains in already-elected/excluded neighbors.
     *
     * @param list<string> $requestedCluster
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @return list<string>|null
     */
    private function findMatchingCluster(array $requestedCluster, array $bands): ?array
    {
        $target = $this->signature($requestedCluster);

        foreach ($bands as $band) {
            if ($this->signature($band['candidates']) === $target) {
                return $band['candidates'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $names
     */
    private function signature(array $names): string
    {
        $sorted = $names;
        sort($sorted);

        return implode("\x01", $sorted);
    }

    /**
     * Validated exactly as RunnerResolutionApplier validates a stored
     * resolution: --order must be a permutation of the cluster, and must not
     * place any candidate above another the votes already locked below it.
     *
     * @param list<string> $cluster
     * @param list<string> $order
     * @param array<string,array<string,bool>> $reachable
     */
    private function isValidResolution(array $cluster, array $order, array $reachable): bool
    {
        $sortedCluster = $cluster;
        sort($sortedCluster);
        $sortedOrder = $order;
        sort($sortedOrder);

        if ($sortedCluster !== $sortedOrder) {
            return false;
        }

        $count = count($order);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($reachable[$order[$j]][$order[$i]] ?? false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Recomputes the Ranked-Pairs locked reachability relation straight from
     * the ballot's cast votes. Mirrors OrderedList::calculateResults's tally
     * roster (deduped, first occurrence wins, via `array_unique`) plus
     * accountAndParse's ballot cleaning (in-roster, distinct, scalar, order
     * preserved) minus the accounting tallies this command has no use for;
     * that logic is private to the component, so this is the light
     * duplication of a CLI-only concern rather than widening OrderedList's
     * public surface for it. The roster MUST be deduped identically to
     * calculateResults's, or a duplicate-label roster could feed
     * PairwiseMatrix/RankedPairsLock a different candidate set than the one
     * the published result was actually computed over, shifting which edge
     * Ranked Pairs drops and changing `reachable` for the --order validation.
     *
     * @return array<string,array<string,bool>>
     */
    private function reachableFromVotes(\App\Models\Ballot $ballot, BallotComponent $component): array
    {
        $roster = array_values(array_unique(array_map('strval', $component->options ?? [])));

        /** @var list<list<string>> $counted */
        $counted = [];
        foreach ($ballot->castVotes() as $vote) {
            $values = $vote->values ?? null;
            $ranking = (is_array($values) && array_key_exists($component->id, $values))
                ? $values[$component->id]
                : null;

            if (!is_array($ranking) || $ranking === []) {
                continue;
            }

            $clean = [];
            foreach ($ranking as $pref) {
                if (!is_scalar($pref)) {
                    continue;
                }
                $label = (string) $pref;
                if (!in_array($label, $roster, true) || in_array($label, $clean, true)) {
                    continue;
                }
                $clean[] = $label;
            }

            if ($clean !== []) {
                $counted[] = $clean;
            }
        }

        $pairwiseMatrix = new PairwiseMatrix($counted, $roster);
        $lock = new RankedPairsLock($pairwiseMatrix->decisivePairs(), $roster);

        return $lock->reachable();
    }
}
