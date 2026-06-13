<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class ElectionShow extends Command
{
    protected $signature = 'evote:show:election
                            {--E|election= : The election ID}';

    protected $description = 'Show details of an election';

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
                'Select an election',
                $elections->pluck('title')->toArray()
            );
            $election = $elections->firstWhere('title', $choice);
        }

        $this->table(['Field', 'Value'], [
            ['ID', $election->id],
            ['Title', $election->title],
            ['Description', $election->description],
            ['Level', $election->level],
            ['Owner', $election->owner],
            ['Abstainable', $election->abstainable ? 'Yes' : 'No'],
            ['Active', $election->active ? 'Yes' : 'No'],
            ['Locked', $election->locked ? 'Yes' : 'No'],
            ['Created At', $election->created_at],
        ]);

        $ballots = $election->ballots;

        $this->newLine();
        $this->info('Ballots:');
        $this->table(
            ['ID', 'Title', 'Active', 'Finished'],
            $ballots->map(fn ($b): array => [
                $b->id,
                $b->title,
                $b->active ? 'Yes' : 'No',
                $b->finished ? 'Yes' : 'No',
            ])
        );

        return 0;
    }
}
