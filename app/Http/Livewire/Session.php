<?php

namespace App\Http\Livewire;

use App\Models\Ballot;
use App\Models\BallotComponent;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Http\Request;
use Livewire\Component;
use Illuminate\Support\Facades\Redis;


class Session extends Component
{

    public Election $election;
    public Ballot $ballot;
    public string $code;
    /**
     * @var BallotComponent[]
     */
    public array $activeComponents;

    public function mount(Election $election, Ballot $ballot, Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $this->election = $election;
        $this->ballot = $ballot;

        $vote = Vote::find(['id' => $request->query('code')])->first();
        $this->code = $vote->id ?? 'preview-mode';
    }

    public function render()
    {
        $this->activeComponents = $this->ballot->components()->get()->filter(function ($component) {
            return $component->active;
        });

        if ($this->code !== 'preview-mode') {
            Redis::set("session:active-voters:{$this->ballot->id}:{$this->code}", 1, ['ex' => 60]);
        }

        return view('livewire.session-ballot', ['ballot' => $this->ballot])->extends('layouts.main')->slot('content');
    }
}
