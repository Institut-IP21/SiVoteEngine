<?php

namespace App\Http\Livewire;

use Illuminate\Contracts\View\View;
use App\Models\Ballot;
use App\Models\BallotComponent;
use Illuminate\Support\Collection;
use Livewire\Component;


class RankedChoiceLivewire extends Component
{

    public Ballot $ballot;
    public BallotComponent $component;
    /** @var Collection<int, array{name: string, rank: int|null}> */
    public Collection $rankees;
    /** @var Collection<int, array{name: string, rank: int|null}> */
    public Collection $selected;
    /** @var Collection<int, array{name: string, rank: int|null}> */
    public Collection $unselected;

    public function mount(Ballot $ballot, BallotComponent $component): void
    {
        $this->ballot = $ballot;
        $this->component = $component;
        /** @var Collection<int, array{name: string, rank: int|null}> $rankees */
        $rankees = collect($this->component->options)->map(fn(mixed $option): array => [
            'name' => (string) $option,
            'rank' => null,
        ]);
        $this->rankees = $rankees;
    }

    public function select(string $option): void
    {
        $this->rankees = $this->rankees->map(function (array $rankee) use ($option): array {
            if ($rankee['name'] === $option) {
                $rankee['rank'] = (int) $this->rankees->max('rank') + 1;
            }
            return $rankee;
        });
    }

    public function up(string $option): void
    {
        /** @var array{name: string, rank: int|null} $targetRankee */
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

    public function down(string $option): void
    {
        /** @var array{name: string, rank: int|null} $targetRankee */
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

    public function remove(string $option): void
    {
        /** @var array{name: string, rank: int|null} $targetRankee */
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

    public function render(): View
    {
        /** @var Collection<int<0,1>, Collection<int, array{name: string, rank: int|null}>> $partitioned */
        $partitioned = $this->rankees->partition(fn(array $rankee): bool => $rankee['rank'] !== null);
        /** @var Collection<int, array{name: string, rank: int|null}> $selected */
        /** @var Collection<int, array{name: string, rank: int|null}> $unselected */
        [$selected, $unselected] = $partitioned;
        $this->selected = $selected->sortBy('rank')->values();
        $this->unselected = $unselected;
        /** @var view-string $template */
        $template = $this->component->form_template_livewire;
        return view($template);
    }
}
