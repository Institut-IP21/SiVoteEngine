<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use Illuminate\Console\Command;

class BallotDelete extends Command
{
    protected $signature = 'evote:delete:ballot
                            {--B|ballot= : The ballot ID}';

    protected $description = 'Delete a ballot (soft delete)';

    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        if ($ballotId) {
            $ballot = Ballot::find($ballotId);
        }

        if (empty($ballot)) {
            $ballots = Ballot::where('active', false)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($ballots->isEmpty()) {
                $this->error('No deletable ballots found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a ballot to delete',
                $ballots->pluck('title')->toArray()
            );
            $ballot = $ballots->firstWhere('title', $choice);
        }

        if ($ballot->active) {
            $this->error('Cannot delete an active ballot. Deactivate it first.');
            return 1;
        }

        if (!$this->confirm("Are you sure you want to delete ballot '{$ballot->title}'?")) {
            $this->warn('Cancelled');
            return 0;
        }

        $ballot->delete();
        $this->info("Ballot '{$ballot->title}' has been deleted");

        return 0;
    }
}
