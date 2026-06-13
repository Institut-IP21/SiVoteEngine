<?php

namespace App\Console\Commands;

use App\Models\BallotComponent;
use Illuminate\Console\Command;

class BallotComponentDelete extends Command
{
    protected $signature = 'evote:delete:ballot:component
                            {--C|component= : The ballot component ID}';

    protected $description = 'Delete a ballot component (permanent)';

    public function handle(): int
    {
        $componentId = $this->option('component');

        if ($componentId) {
            $component = BallotComponent::find($componentId);
        }

        if (empty($component)) {
            $components = BallotComponent::orderBy('created_at', 'desc')->get();

            if ($components->isEmpty()) {
                $this->error('No components found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a component to delete',
                $components->pluck('title')->toArray()
            );
            $component = $components->firstWhere('title', $choice);
        }

        /** @var BallotComponent $component */
        if (!$this->confirm("Are you sure you want to delete component '{$component->title}'?")) {
            $this->warn('Cancelled');
            return 0;
        }

        $component->delete();
        $this->info("Component '{$component->title}' has been deleted");

        return 0;
    }
}
