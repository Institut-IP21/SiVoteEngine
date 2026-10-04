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
     * @param array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,partly_infeasible:bool,provisional:bool,binding:bool,too_complex:bool,seated:list<string>,contested:list<string>,positions:array<string,int>}|null $corrected
     * @param array{strength:array<string,array<string,int|null>>,winners:list<array{winner:string,loser:string,strength:int,path:list<string>}>} $beatpath
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
        public ?array $corrected,
        public string $official,
        public array $beatpath,
        public array $pairwise,
        public array $accounting,
        public array $warnings,
    ) {}

    /*
     * The OFFICIAL outcome -- the single source every consumer (result page,
     * tally CSV, web_app) reads instead of re-deriving it. It is the binding
     * quota's slate when `official === 'corrected'`, otherwise the votes-
     * alone slate. In both cases the engine reports only what is certain:
     * who is surely seated, the seats whose occupant is certain, and who is
     * still contested because of a genuine tie.
     */

    /**
     * Surely-seated candidates, certain positions first.
     *
     * @return list<string>
     */
    public function officialOrder(): array
    {
        return $this->officialCorrected() !== null ? $this->officialCorrected()['seated'] : $this->elected;
    }

    /**
     * Candidate => 1-based seat, for every seat whose occupant is certain.
     *
     * @return array<string,int>
     */
    public function officialPositions(): array
    {
        if ($this->officialCorrected() !== null) {
            return $this->officialCorrected()['positions'];
        }

        $positions = [];
        foreach ($this->ranking as $entry) {
            if ($entry['status'] === 'elected' && $entry['determined']) {
                $positions[$entry['candidate']] = $entry['best_pos'];
            }
        }

        return $positions;
    }

    /**
     * Candidates who may or may not be seated, pending a genuine tie.
     *
     * @return list<string>
     */
    public function officialContested(): array
    {
        if ($this->officialCorrected() !== null) {
            return $this->officialCorrected()['contested'];
        }

        return $this->cutoffDecision['candidates'] ?? [];
    }

    /**
     * Every seat has a certain occupant, in a certain order.
     */
    public function isFinal(): bool
    {
        if ($this->ranking === []) {
            return false;
        }

        $order = $this->officialOrder();

        return $this->officialContested() === []
            && count($order) === min($this->seats, count($this->ranking))
            && count($this->officialPositions()) === count($order);
    }

    /**
     * @return array{order:list<string>,diff:list<array{candidate:string,from:string,reason:string}>,infeasible:bool,partly_infeasible:bool,provisional:bool,binding:bool,too_complex:bool,seated:list<string>,contested:list<string>,positions:array<string,int>}|null
     */
    private function officialCorrected(): ?array
    {
        return $this->official === 'corrected' ? $this->corrected : null;
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
            'official_positions' => $this->officialPositions(),
            'official_contested' => $this->officialContested(),
            'final' => $this->isFinal(),
            'beatpath' => $this->beatpath,
            'pairwise' => $this->pairwise,
            'accounting' => $this->accounting,
            'warnings' => $this->warnings,
        ];
    }
}
