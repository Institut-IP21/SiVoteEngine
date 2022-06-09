<?php

namespace App\Http\Livewire;

use App\Models\Ballot;
use App\Models\BallotComponent;
use Illuminate\Support\Collection;
use Livewire\Component;


class RankedChoiceLivewire extends Component
{

    public Ballot $ballot;
    public BallotComponent $component;
    public Collection $rankees;
    public Collection $selected;
    public Collection $unselected;

    public function mount(Ballot $ballot, BallotComponent $component)
    {
        $this->ballot = $ballot;
        $this->component = $component;
        $this->rankees = collect($this->component->options)->map(function ($option) {
            return [
                'name' => $option,
                'rank' => null
            ];
        });
    }

    public function select($option)
    {
        $this->rankees = $this->rankees->map(function ($rankee) use ($option) {
            if ($rankee['name'] === $option) {
                $rankee['rank'] = $this->rankees->max('rank') + 1;
            }
            return $rankee;
        });
    }

    public function up($option)
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function ($rankee) use ($targetRankee) {
            if ($rankee['rank'] === $targetRankee['rank'] - 1) {
                $rankee['rank'] += 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
    }

    public function down($option)
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function ($rankee) use ($targetRankee) {
            if ($rankee['rank'] === $targetRankee['rank'] + 1) {
                $rankee['rank'] -= 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] += 1;
            }

            return $rankee;
        });
    }

    public function remove($option)
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function ($rankee) use ($targetRankee) {
            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] = null;
            }
            if ($rankee['rank'] > $targetRankee['rank']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
    }

    public function render()
    {
        [$selected, $unselected] = $this->rankees->partition(function ($rankee) {
            return $rankee['rank'] !== null;
        });
        $this->selected = $selected->sortBy('rank')->values();
        $this->unselected = $unselected;
        return view($this->component->form_template_livewire);
    }
}
