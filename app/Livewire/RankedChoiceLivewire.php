<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
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

    public function mount(Ballot $ballot, BallotComponent $component): void
    {
        $this->ballot = $ballot;
        $this->component = $component;
        $this->rankees = collect($this->component->options)->map(fn($option) => [
            'name' => $option,
            'rank' => null
        ]);
    }

    public function select($option): void
    {
        $this->rankees = $this->rankees->map(function (array $rankee) use ($option): array {
            if ($rankee['name'] === $option) {
                $rankee['rank'] = $this->rankees->max('rank') + 1;
            }
            return $rankee;
        });
    }

    public function up($option): void
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['rank'] === $targetRankee['rank'] - 1) {
                $rankee['rank'] += 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
    }

    public function down($option): void
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['rank'] === $targetRankee['rank'] + 1) {
                $rankee['rank'] -= 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] += 1;
            }

            return $rankee;
        });
    }

    public function remove($option): void
    {
        $targetRankee = $this->rankees->where('name', $option)->first();

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] = null;
            }
            if ($rankee['rank'] > $targetRankee['rank']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
    }

    public function render(): Factory|View
    {
        [$selected, $unselected] = $this->rankees->partition(fn($rankee) => $rankee['rank'] !== null);
        $this->selected = $selected->sortBy('rank')->values();
        $this->unselected = $unselected;
        return view($this->component->form_template_livewire);
    }
}
