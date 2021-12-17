<?php

namespace App\Http\Livewire;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use Illuminate\Http\Request;
use Livewire\Component;
use Illuminate\Support\Facades\Redis;


class Session extends Component
{
    public function mount(Election $election, Ballot $ballot, Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $this->election = $election;
        $this->ballot = $ballot;

        $vote = Vote::find(['id' => $request->query('code')])->first();
        $this->code = $vote->id ?? 'predogled';
    }

    public function render()
    {
        $this->activeComponents = $this->ballot->components()->get()->filter(function ($component) {
            return $component->active;
        });

        if ($this->code !== 'predogled') {
            Redis::set("session:active-voters:{$this->ballot->id}:{$this->code}", 1, 'EX', 30);
        }

        return view('livewire.session-ballot', ['ballot' => $this->ballot])->extends('layouts.main')->slot('content');
    }
}
