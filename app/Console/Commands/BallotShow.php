<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use Illuminate\Console\Command;

class BallotShow extends Command
{
    protected $signature = 'evote:show:ballot
                            {--B|ballot= : The ballot ID}';

    protected $description = 'Show details of a ballot';

    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        if ($ballotId) {
            $ballot = Ballot::find($ballotId);
        }

        if (empty($ballot)) {
            $ballots = Ballot::orderBy('created_at', 'desc')->get();

            if ($ballots->isEmpty()) {
                $this->error('No ballots found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a ballot',
                $ballots->pluck('title')->toArray()
            );
            $ballot = $ballots->firstWhere('title', $choice);
        }

        $this->table(['Field', 'Value'], [
            ['ID', $ballot->id],
            ['Title', $ballot->title],
            ['Election', $ballot->election->title],
            ['Active', $ballot->active ? 'Yes' : 'No'],
            ['Finished', $ballot->finished ? 'Yes' : 'No'],
            ['Locked', $ballot->locked ? 'Yes' : 'No'],
            ['Mode', $ballot->mode],
            ['Is Secret', $ballot->is_secret ? 'Yes' : 'No'],
            ['Quorum', $ballot->quorum ?? 'N/A'],
            ['Votes Count', $ballot->votes_count],
        ]);

        $components = $ballot->components()->get();

        $this->newLine();
        $this->info('Components:');
        $this->table(
            ['ID', 'Title', 'Type', 'Version', 'Active', 'Order'],
            $components->map(fn ($c) => [
                $c->id,
                $c->title,
                $c->type,
                $c->version,
                $c->active ? 'Yes' : 'No',
                $c->order,
            ])
        );

        return 0;
    }
}
