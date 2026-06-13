<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use Illuminate\Console\Command;

class BallotDeactivate extends Command
{
    protected $signature = 'evote:deactivate:ballot
                            {--B|ballot= : The ballot ID}';

    protected $description = 'Deactivate a ballot to close voting';

    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        if ($ballotId) {
            $ballot = Ballot::find($ballotId);
        }

        if (empty($ballot)) {
            $ballots = Ballot::where('active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($ballots->isEmpty()) {
                $this->error('No active ballots found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a ballot to deactivate',
                $ballots->pluck('title')->toArray()
            );
            $ballot = $ballots->firstWhere('title', $choice);
        }

        /** @var Ballot $ballot */
        if (!$ballot->active) {
            $this->error('Ballot is not currently active');
            return 1;
        }

        if (!$this->confirm("This will permanently close voting on ballot '{$ballot->title}'. Continue?", false)) {
            $this->warn('Cancelled');
            return 0;
        }

        $ballot->deactivate();
        $this->info("Ballot '{$ballot->title}' has been deactivated");

        return 0;
    }
}
