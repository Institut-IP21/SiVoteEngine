<?php

namespace App\Livewire;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Vote;
use App\Services\BallotService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\ActiveSessionVoter;

#[Layout('layouts.main')]
class Session extends Component
{

    public Election $election;
    public Ballot $ballot;
    public string $code;
    public Collection $activeComponents;

    public function mount(Election $election, Ballot $ballot, Request $request, BallotService $service)
    {
        if (!$request->hasValidSignature()) {
            abort(401);
        }

        $this->election = $election;
        $this->ballot = $ballot;
        $this->applyElectionLocale();
        $this->componentTree = $service->getComponentTree();

        $vote = Vote::find(['id' => $request->query('code')])->first();
        $this->code = $vote->id ?? 'preview-mode';
    }

    /**
     * Render the session ballot in the locale the election was organized in
     * (set on every Livewire request, not just initial mount).
     */
    private function applyElectionLocale(): void
    {
        if (!empty($this->election->locale)) {
            app()->setLocale($this->election->locale);
        }
    }

    public function render()
    {
        $this->applyElectionLocale();

        $this->activeComponents = $this->ballot->components()->get()->filter(function ($component) {
            return $component->active;
        });

        if ($this->code !== 'preview-mode') {
            ActiveSessionVoter::updateOrCreate(
                ['ballot_id' => $this->ballot->id, 'code' => $this->code],
                ['last_seen_at' => now()]
            );
        }

        return view('livewire.session-ballot', ['ballot' => $this->ballot]);
    }
}
