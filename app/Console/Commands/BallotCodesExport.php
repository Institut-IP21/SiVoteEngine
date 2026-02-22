<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\Vote;
use Illuminate\Console\Command;

class BallotCodesExport extends Command
{
    protected $signature = 'evote:export:codes
                            {--B|ballot= : The ballot ID}
                            {--F|file= : Output file path (stdout if not specified)}';

    protected $description = 'Export uncast vote codes as CSV';

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

        $votes = Vote::where('ballot_id', $ballot->id)
            ->whereNull('values')
            ->get();

        $baseUrl = config('app.url');
        $lines = ["code,url"];

        foreach ($votes as $vote) {
            $lines[] = "{$vote->id},{$baseUrl}/vote/{$vote->id}";
        }

        $csv = implode("\n", $lines);

        $file = $this->option('file');
        if ($file) {
            file_put_contents($file, $csv . "\n");
            $this->info("Exported {$votes->count()} codes to {$file}");
        } else {
            $this->line($csv);
        }

        return 0;
    }
}
