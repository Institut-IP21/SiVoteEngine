<?php

namespace App\Http\Livewire;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Component;
use Illuminate\Support\Facades\Redis;

class Session extends Component
{

    public Election $election;
    public Ballot $ballot;
    public string $code;
    public Collection $activeComponents;
    public array $componentTree = [];

    public function mount(Election $election, Ballot $ballot, Request $request, BallotService $service)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $this->election = $election;
        $this->ballot = $ballot;
        $this->componentTree = $service->getComponentTree();

        $vote = Vote::find(['id' => $request->query('code')])->first();
        $this->code = $vote->id ?? 'preview-mode';
    }

    public function render()
    {
        $this->activeComponents = $this->ballot->components()->get()->filter(function (BallotComponent $component) {
            return $component->active;
        });

        if ($this->code !== 'preview-mode') {
            Redis::setex("session:active-voters:{$this->ballot->id}:{$this->code}", 60, 1);
        }

        return view('livewire.session-ballot', ['ballot' => $this->ballot])->extends('layouts.main')->slot('content');
    }
}
