<?php

namespace App\Http\Livewire;

use App\Models\Election as ModelsElection;
use App\Models\Vote;
use Illuminate\Http\Request;
use Livewire\Component;

class Session extends Component
{
    public function mount(ModelsElection $election, Request $request)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $this->election = $election;
        $this->counter = 0;
        $vote = Vote::find(['id' => $request->query('code')])->first();
        $this->code = $vote->id;
    }

    public function render()
    {
        return view('livewire.session-election', ['election' => $this->election])->extends('layouts.main')->slot('content');
    }

    public function clack()
    {
        $this->counter++;
    }
}
