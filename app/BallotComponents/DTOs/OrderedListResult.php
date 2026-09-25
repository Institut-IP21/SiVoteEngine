<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of a Ranked-Pairs (margins) ordered-list tally (OrderedList/v1).
 * The votes lock a partial order; anywhere they don't settle an order, it is
 * surfaced as an unresolved "band" (or a "cutoff decision" at the seat
 * boundary) for the election runner to resolve post-close. No arbitrary
 * tiebreak is ever applied by the engine.
 */
final readonly class OrderedListResult implements ComponentResult
{
    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<string> $elected
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null $cutoffDecision
     * @param list<array{cluster:list<string>,order:list<string>,comment:string,resolved_by:string,resolved_at:string}> $resolutions
     * @param array{order:list<array{position:int,candidate:string,tied:bool}>,complete:bool}|null $final
     * @param array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}|null $corrected
     * @param list<array{type:string,winner:string,loser:string,for:int,against:int,margin:int,members?:list<string>}> $lockInLog
     * @param array{candidates:list<string>,matrix:array<string,array<string,int>>} $pairwise
     * @param array{cast:int,blank:int,invalid_only:int,counted:int} $accounting
     * @param list<string> $warnings
     */
    public function __construct(
        public int $seats,
        public array $ranking,
        public array $elected,
        public array $bands,
        public ?array $cutoffDecision,
        public array $resolutions,
        public ?array $final,
        public ?array $corrected,
        public string $official,
        public array $lockInLog,
        public array $pairwise,
        public array $accounting,
        public array $warnings,
    ) {}

    public static function empty(int $seats): self
    {
        return new self(
            seats: $seats,
            ranking: [],
            elected: [],
            bands: [],
            cutoffDecision: null,
            resolutions: [],
            final: null,
            corrected: null,
            official: 'natural',
            lockInLog: [],
            pairwise: ['candidates' => [], 'matrix' => []],
            accounting: ['cast' => 0, 'blank' => 0, 'invalid_only' => 0, 'counted' => 0],
            warnings: [],
        );
    }

    #[\Override]
    public function toArray(): array
    {
        return [
            'seats' => $this->seats,
            'strength_measure' => 'margins',
            'ranking' => $this->ranking,
            'elected' => $this->elected,
            'bands' => $this->bands,
            'cutoff_decision' => $this->cutoffDecision,
            'resolutions' => $this->resolutions,
            'final' => $this->final,
            'corrected' => $this->corrected,
            'official' => $this->official,
            'lock_in_log' => $this->lockInLog,
            'pairwise' => $this->pairwise,
            'accounting' => $this->accounting,
            'warnings' => $this->warnings,
        ];
    }
}
