<?php

declare(strict_types=1);

namespace App\BallotComponents\DTOs;

/**
 * Result of a Schulze method (beatpath, margins variant) ordered-list tally
 * (OrderedList/v1). The votes settle a strict partial order; anywhere they
 * don't settle an order, it is surfaced as an unresolved "band" (or a
 * "cutoff decision" at the seat boundary) for the organization to resolve
 * per its own rules. No arbitrary tiebreak is ever applied by the engine,
 * and no resolution is ever recorded or applied by the engine itself.
 */
final readonly class OrderedListResult implements ComponentResult
{
    /**
     * @param list<array{candidate:string,best_pos:int,worst_pos:int,determined:bool,status:string}> $ranking
     * @param list<string> $elected
     * @param list<array{candidates:list<string>,span:array{0:int,1:int},internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>,affects_cutoff:bool}> $bands
     * @param array{remaining_seats:int,candidates:list<string>,internal_constraints:list<array{winner:string,loser:string}>,head_to_head:array<string,array<string,int>>}|null $cutoffDecision
     * @param array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,provisional:bool,binding:bool}|null $corrected
     * @param array{strength:array<string,array<string,int|null>>,winners:list<array{winner:string,loser:string,strength:int,path:list<string>}>} $beatpath
     * @param array{candidates:list<string>,matrix:array<string,array<string,int>>} $pairwise
     * @param array{cast:int,blank:int,invalid_only:int,counted:int} $accounting
     * @param list<string> $warnings
     * @param bool $final the engine's single finality predicate -- every
     *        consumer (result page, tally CSV, web_app) reads this instead of
     *        re-deriving it from bands/quota state.
     */
    public function __construct(
        public int $seats,
        public array $ranking,
        public array $elected,
        public array $bands,
        public ?array $cutoffDecision,
        public ?array $corrected,
        public string $official,
        public array $beatpath,
        public array $pairwise,
        public array $accounting,
        public array $warnings,
        public bool $final = false,
    ) {}

    /**
     * The OFFICIAL slate: the binding quota's corrected order when that is
     * the official result, otherwise the natural (surely-)elected list.
     *
     * @return list<string>
     */
    public function officialOrder(): array
    {
        return ($this->official === 'corrected' && $this->corrected !== null)
            ? $this->corrected['order']
            : $this->elected;
    }

    public static function empty(int $seats): self
    {
        return new self(
            seats: $seats,
            ranking: [],
            elected: [],
            bands: [],
            cutoffDecision: null,
            corrected: null,
            official: 'natural',
            beatpath: ['strength' => [], 'winners' => []],
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
            'corrected' => $this->corrected,
            'official' => $this->official,
            'official_order' => $this->officialOrder(),
            'final' => $this->final,
            'beatpath' => $this->beatpath,
            'pairwise' => $this->pairwise,
            'accounting' => $this->accounting,
            'warnings' => $this->warnings,
        ];
    }
}
