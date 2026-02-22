<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class ElectionDelete extends Command
{
    protected $signature = 'evote:delete:election
                            {--E|election= : The election ID}';

    protected $description = 'Delete an election (soft delete)';

    public function handle(): int
    {
        $electionId = $this->option('election');

        if ($electionId) {
            $election = Election::find($electionId);
        }

        if (empty($election)) {
            $elections = Election::orderBy('created_at', 'desc')->get();

            if ($elections->isEmpty()) {
                $this->error('No elections found.');
                return 1;
            }

            $choice = $this->choice(
                'Select an election to delete',
                $elections->pluck('title')->toArray()
            );
            $election = $elections->firstWhere('title', $choice);
        }

        if ($election->active) {
            $this->error('Cannot delete an election with active ballots');
            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete election '{$election->title}'?")) {
            $this->warn('Cancelled');
            return 0;
        }

        $election->delete();
        $this->info("Election '{$election->title}' has been deleted");

        return 0;
    }
}
