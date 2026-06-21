<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use App\Models\Ballot;
use App\Models\BallotComponent;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;


class RankedChoiceLivewire extends Component
{

    public Ballot $ballot;
    public BallotComponent $component;
    /** @var Collection<array-key, mixed> */
    public Collection $rankees;
    /** @var Collection<array-key, mixed> */
    public Collection $selected;
    /** @var Collection<array-key, mixed> */
    public Collection $unselected;
    /** Latest change, announced to screen readers via an aria-live region. Server-set
     *  only (#[Locked] — the client may not overwrite it). */
    #[Locked]
    public string $announce = '';

    /** Whether the election allows abstaining (ranking nothing = an abstention here). */
    #[Locked]
    public bool $abstainable = false;

    public function mount(Ballot $ballot, BallotComponent $component): void
    {
        $this->ballot = $ballot;
        $this->component = $component;
        $this->abstainable = (bool) $ballot->election?->abstainable;
        $this->rankees = collect($this->component->options)->map(fn($option) => [
            'name' => $option,
            'rank' => null
        ]);
    }

    public function select(string $option): void
    {
        $this->rankees = $this->rankees->map(function (array $rankee) use ($option): array {
            if ($rankee['name'] === $option) {
                $rankee['rank'] = $this->rankees->max('rank') + 1;
            }
            return $rankee;
        });
        $this->announce(messageKey: 'announce_added', name: $option);
    }

    public function up(string $option): void
    {
        /** @var array<string, mixed>|null $targetRankee */
        $targetRankee = $this->rankees->where('name', $option)->first();
        // Guard: unknown/unranked option, or already first — nothing above to swap with.
        if ($targetRankee === null || $targetRankee['rank'] === null || $targetRankee['rank'] <= 1) {
            return;
        }

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['rank'] === $targetRankee['rank'] - 1) {
                $rankee['rank'] += 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
        $this->announce(messageKey: 'announce_moved', name: $option);
    }

    public function down(string $option): void
    {
        /** @var array<string, mixed>|null $targetRankee */
        $targetRankee = $this->rankees->where('name', $option)->first();
        // Guard: unknown/unranked option, or already last — nothing below to swap with.
        if ($targetRankee === null || $targetRankee['rank'] === null || $targetRankee['rank'] >= $this->rankees->max('rank')) {
            return;
        }

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['rank'] === $targetRankee['rank'] + 1) {
                $rankee['rank'] -= 1;
            }

            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] += 1;
            }

            return $rankee;
        });
        $this->announce(messageKey: 'announce_moved', name: $option);
    }

    /** Promote a ranked option straight to first preference (no repeated "up" taps). */
    public function moveToTop(string $option): void
    {
        /** @var array<string, mixed>|null $targetRankee */
        $targetRankee = $this->rankees->where('name', $option)->first();
        if ($targetRankee === null || $targetRankee['rank'] === null) {
            return;
        }
        $from = $targetRankee['rank'];

        $this->rankees = $this->rankees->map(function (array $rankee) use ($option, $from): array {
            if ($rankee['name'] === $option) {
                $rankee['rank'] = 1;
            } elseif ($rankee['rank'] !== null && $rankee['rank'] < $from) {
                $rankee['rank'] += 1;
            }
            return $rankee;
        });
        $this->announce(messageKey: 'announce_moved', name: $option);
    }

    public function remove(string $option): void
    {
        /** @var array<string, mixed>|null $targetRankee */
        $targetRankee = $this->rankees->where('name', $option)->first();
        // Guard: unknown or already-unranked option.
        if ($targetRankee === null || $targetRankee['rank'] === null) {
            return;
        }

        $this->rankees = $this->rankees->map(function (array $rankee) use ($targetRankee): array {
            if ($rankee['name'] === $targetRankee['name']) {
                $rankee['rank'] = null;
            }
            if ($rankee['rank'] > $targetRankee['rank']) {
                $rankee['rank'] -= 1;
            }
            return $rankee;
        });
        $this->announce(messageKey: 'announce_removed', name: $option);
    }

    /**
     * Re-rank from a complete ordered list of names (used by drag-to-reorder).
     * Names not in $names become unranked; unknown names are ignored; ranks stay 1..n.
     *
     * @param  array<int, mixed>  $names
     */
    public function setOrder(array $names): void
    {
        /** @var Collection<int, string> $order */
        $order = collect($names)
            ->filter(fn($n): bool => is_string($n) && in_array($n, $this->component->options, true))
            ->unique()
            ->values();

        $this->rankees = $this->rankees->map(function (array $rankee) use ($order): array {
            $index = $order->search($rankee['name'], strict: true);
            $rankee['rank'] = $index === false ? null : ((int) $index) + 1;
            return $rankee;
        });
    }

    /** Set the aria-live message for the last action. */
    private function announce(string $messageKey, string $name): void
    {
        $params = ['name' => $name];
        if ($messageKey !== 'announce_removed') {
            /** @var array<string, mixed>|null $rankee */
            $rankee = $this->rankees->where('name', $name)->first();
            $params['rank'] = $rankee['rank'] ?? '';
        }
        $this->announce = (string) __('components.rankedchoice.' . $messageKey, $params);
    }

    public function render(): Factory|View
    {
        /** @var Collection<array-key, mixed> $selected */
        /** @var Collection<array-key, mixed> $unselected */
        [$selected, $unselected] = $this->rankees->partition(fn($rankee) => $rankee['rank'] !== null);
        $this->selected = $selected->sortBy('rank')->values();
        $this->unselected = $unselected;
        /** @var view-string $template */
        $template = $this->component->form_template_livewire;
        return view($template);
    }
}
