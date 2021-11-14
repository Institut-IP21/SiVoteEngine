<?php

namespace App\Http\Livewire;

use App\Models\Election as ModelsElection;
use Livewire\Component;

class Session extends Component
{
    public function mount(ModelsElection $election)
    {
        $this->election = $election;
        $this->counter = 0;
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
