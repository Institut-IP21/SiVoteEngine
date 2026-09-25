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
    // $seats is part of the class's public contract (it mirrors the other
    // calc classes so callers can pass it through uniformly) but the
    // assembly algorithm needs no seat-relative logic of its own: every
    // cluster it resolves was already classified by PositionResolver.
    // @phpstan-ignore constructor.unusedParameter
    public function __construct(array $ranking, array $bands, ?array $cutoffDecision, array $resolutions, array $reachable, int $seats)
    {
        /** @var list<list<string>> $clusters */
        $clusters = [];
        /** @var array<string,int> $seenSignatures */
        $seenSignatures = [];

        foreach ($bands as $band) {
            $sig = $this->signature($band['candidates']);
            if (!isset($seenSignatures[$sig])) {
                $seenSignatures[$sig] = count($clusters);
                $clusters[] = $band['candidates'];
            }
        }
        if ($cutoffDecision !== null) {
            $sig = $this->signature($cutoffDecision['candidates']);
            if (!isset($seenSignatures[$sig])) {
                $seenSignatures[$sig] = count($clusters);
                $clusters[] = $cutoffDecision['candidates'];
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

            if (!$this->isValidResolution($members, $resolution['order'], $reachable)) {
                $this->warnings[] = sprintf(
                    'resolution for %s rejected: not a permutation / contradicts a locked result',
                    implode(', ', $members)
                );
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

        $this->result = $this->assemble($ranking, $clusters, $clusterOfCandidate, $resolvedOrderOfCluster);
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
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<list<string>> $clusters
     * @param array<string,int> $clusterOfCandidate
     * @param array<int,list<string>|null> $resolvedOrderOfCluster
     * @return array{order:list<array{position:int,candidate:string,tied:bool}>,complete:bool}
     */
    private function assemble(array $ranking, array $clusters, array $clusterOfCandidate, array $resolvedOrderOfCluster): array
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

        $complete = true;
        foreach ($clusters as $idx => $members) {
            if (($resolvedOrderOfCluster[$idx] ?? null) === null) {
                $complete = false;

                break;
            }
        }

        return ['order' => $order, 'complete' => $complete];
    }
}
