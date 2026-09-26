<?php

declare(strict_types=1);

namespace App\BallotComponents\OrderedList\v1;

/**
 * Applies post-close runner resolutions to the unresolved bands (and the
 * cutoff cluster, when it exists) surfaced by PositionResolver. A stored
 * resolution is matched to a cluster by candidate-set equality and, once
 * matched, validated against the locked partial order: it is never allowed
 * to silently reorder the list against a fact the votes already settled.
 */
final class RunnerResolutionApplier
{
    /** @var array{order:list<array{position:int,candidate:string,tied:bool}>,complete:bool} */
    private array $result;

    private bool $applied = false;

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null $cutoffDecision
     * @param list<array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}> $resolutions
     * @param array<string,array<string,bool>> $reachable
     */
    // $cutoffDecision is part of the class's public contract (it mirrors the
    // shape PositionResolver exposes, and keeps this constructor's signature
    // stable for callers that pass all of PositionResolver's outputs
    // uniformly) but it is no longer a separate resolvable cluster: the
    // cutoff decision is a display/summary + quota-defer signal (derived
    // from candidate status), and the contested candidates it names are
    // always a subset of the single straddling band already resolved via
    // $bands. $seats likewise needs no seat-relative logic of its own here:
    // every cluster it resolves was already classified by PositionResolver.
    // @phpstan-ignore constructor.unusedParameter
    public function __construct(array $ranking, array $bands, ?array $cutoffDecision, array $resolutions, array $reachable, int $seats)
    {
        /** @var list<list<string>> $clusters */
        $clusters = [];
        /** @var array<string,int> $seenSignatures */
        $seenSignatures = [];
        /** @var array<int,bool> $blocking true iff this cluster's band starts at or before the seat cutoff -- a band entirely below the cutoff is display-only and never blocks completeness. */
        $blocking = [];

        foreach ($bands as $band) {
            $sig = $this->signature($band['candidates']);
            if (!isset($seenSignatures[$sig])) {
                $idx = count($clusters);
                $seenSignatures[$sig] = $idx;
                $clusters[] = $band['candidates'];
                $blocking[$idx] = $band['span'][0] <= $seats;
            }
        }

        /** @var array<string,int> $clusterOfCandidate */
        $clusterOfCandidate = [];
        foreach ($clusters as $idx => $members) {
            foreach ($members as $m) {
                $clusterOfCandidate[$m] = $idx;
            }
        }

        /** @var array<int,list<string>|null> $resolvedOrderOfCluster */
        $resolvedOrderOfCluster = [];
        $matchedSignatures = [];

        foreach ($clusters as $idx => $members) {
            $matchedSignatures[$this->signature($members)] = true;
            $resolution = $this->findResolutionFor($members, $resolutions);

            if ($resolution === null) {
                $resolvedOrderOfCluster[$idx] = null;

                continue;
            }

            $rejection = $this->rejectionReason($members, $resolution['order'], $reachable);
            if ($rejection !== null) {
                $this->warnings[] = sprintf('resolution for %s rejected: %s', implode(', ', $members), $rejection);
                $resolvedOrderOfCluster[$idx] = null;

                continue;
            }

            $resolvedOrderOfCluster[$idx] = $resolution['order'];
            $this->applied = true;
        }

        foreach ($resolutions as $resolution) {
            if (!isset($matchedSignatures[$this->signature($resolution['cluster'])])) {
                $this->warnings[] = sprintf(
                    'resolution for %s matches no current tie',
                    implode(', ', $resolution['cluster'])
                );
            }
        }

        $this->result = $this->assemble($ranking, $clusters, $clusterOfCandidate, $resolvedOrderOfCluster, $blocking);
    }

    /** @return array{order:list<array{position:int,candidate:string,tied:bool}>,complete:bool} */
    public function result(): array
    {
        return $this->result;
    }

    public function applied(): bool
    {
        return $this->applied;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @param list<string> $candidates
     */
    private function signature(array $candidates): string
    {
        $sorted = $candidates;
        sort($sorted);

        return implode("\x01", $sorted);
    }

    /**
     * @param list<string> $cluster
     * @param list<array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}> $resolutions
     * @return array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}|null
     */
    private function findResolutionFor(array $cluster, array $resolutions): ?array
    {
        $target = $this->signature($cluster);
        $found = null;

        foreach ($resolutions as $resolution) {
            if ($this->signature($resolution['cluster']) === $target) {
                $found = $resolution;
            }
        }

        return $found;
    }

    /**
     * Validates a resolution against its cluster and, on failure, says
     * exactly which of the two independent rules it broke: an order must
     * (a) be a permutation of the tied set, and separately (b) never place a
     * candidate above another the votes already locked below it. These are
     * reported as two distinct messages rather than one conflated warning.
     *
     * @param list<string> $cluster
     * @param list<string> $order
     * @param array<string,array<string,bool>> $reachable
     */
    private function rejectionReason(array $cluster, array $order, array $reachable): ?string
    {
        $sortedCluster = $cluster;
        sort($sortedCluster);
        $sortedOrder = $order;
        sort($sortedOrder);

        if ($sortedCluster !== $sortedOrder) {
            return 'not a permutation of the tied set';
        }

        $count = count($order);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($reachable[$order[$j]][$order[$i]] ?? false) {
                    return 'contradicts a locked head-to-head result';
                }
            }
        }

        return null;
    }

    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<list<string>> $clusters
     * @param array<string,int> $clusterOfCandidate
     * @param array<int,list<string>|null> $resolvedOrderOfCluster
     * @param array<int,bool> $blocking
     * @return array{order:list<array{position:int,candidate:string,tied:bool}>,complete:bool}
     */
    private function assemble(array $ranking, array $clusters, array $clusterOfCandidate, array $resolvedOrderOfCluster, array $blocking): array
    {
        $order = [];
        $emitted = [];
        $position = 1;

        foreach ($ranking as $entry) {
            $candidate = $entry['candidate'];
            $clusterIdx = $clusterOfCandidate[$candidate] ?? null;

            if ($clusterIdx === null) {
                $order[] = ['position' => $position, 'candidate' => $candidate, 'tied' => false];
                $position++;

                continue;
            }

            if (isset($emitted[$clusterIdx])) {
                continue;
            }
            $emitted[$clusterIdx] = true;

            $resolvedOrder = $resolvedOrderOfCluster[$clusterIdx] ?? null;
            $members = $resolvedOrder ?? $clusters[$clusterIdx];
            $tied = $resolvedOrder === null;

            foreach ($members as $member) {
                $order[] = ['position' => $position, 'candidate' => $member, 'tied' => $tied];
                $position++;
            }
        }

        // A band entirely below the seat cutoff (span[0] > seats) is
        // display-only ordering entanglement among already-excluded
        // candidates: it never blocks the seats that matter from being
        // reported final. Only a band that could still hold one of the K
        // seats (span[0] <= seats) has to be resolved for completeness.
        $complete = true;
        foreach ($clusters as $idx => $members) {
            if (!($blocking[$idx] ?? true)) {
                continue;
            }
            if (($resolvedOrderOfCluster[$idx] ?? null) === null) {
                $complete = false;

                break;
            }
        }

        return ['order' => $order, 'complete' => $complete];
    }
}
