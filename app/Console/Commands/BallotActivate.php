<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use Illuminate\Console\Command;

class BallotActivate extends Command
{
    protected $signature = 'evote:activate:ballot
                            {--B|ballot= : The ballot ID}';

    protected $description = 'Activate a ballot to open voting';

    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        if ($ballotId) {
            $ballot = Ballot::find($ballotId);
        }

        if (empty($ballot)) {
            $ballots = Ballot::where('active', false)
                ->where('finished', false)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($ballots->isEmpty()) {
                $this->error('No activatable ballots found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a ballot to activate',
                $ballots->pluck('title')->toArray()
            );
            $ballot = $ballots->firstWhere('title', $choice);
        }

        if ($ballot->finished) {
            $this->error('Cannot reactivate a finished ballot');
            return 1;
        }

        if ($ballot->active) {
            $this->info('Ballot is already active');
            return 0;
        }

        $ballot->activate();
        $this->info("Ballot '{$ballot->title}' has been activated");

        return 0;
    }
}
