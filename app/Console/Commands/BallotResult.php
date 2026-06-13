<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Services\BallotService;
use Illuminate\Console\Command;

class BallotResult extends Command
{
    protected $signature = 'evote:result:ballot
                            {--B|ballot= : The ballot ID}';

    protected $description = 'Display results for a finished ballot';

    public function __construct(
        private readonly BallotService $ballotService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        if ($ballotId) {
            $ballot = Ballot::find($ballotId);
        }

        if (empty($ballot)) {
            $ballots = Ballot::where('finished', true)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($ballots->isEmpty()) {
                $this->error('No finished ballots found.');
                return 1;
            }

            $choice = $this->choice(
                'Select a ballot',
                $ballots->pluck('title')->toArray()
            );
            $ballot = $ballots->firstWhere('title', $choice);
        }

        if (!$ballot->finished) {
            $this->error('Results are only available for finished ballots');
            return 1;
        }

        $results = $this->ballotService->calculateResults($ballot);

        foreach ($results as $componentId => $componentResult) {
            if ($componentId === '_meta') {
                continue;
            }

            $this->newLine();
            $this->info($componentResult['title']);

            $resultData = $componentResult['results'];

            // Handle state-based results (SimpleVoteResult format)
            if (isset($resultData['state']) && is_array($resultData['state'])) {
                $rows = [];
                foreach ($resultData['state'] as $option => $votes) {
                    $rows[] = [$option, $votes];
                }
                $this->table(['Option', 'Votes'], $rows);

                if (isset($resultData['winner'])) {
                    $this->line("Winner: {$resultData['winner']}");
                }
            } else {
                // Handle generic array-of-rows results
                $rows = collect($resultData)->map(fn($row) => array_values(is_array($row) ? $row : (array) $row));

                if ($rows->isNotEmpty()) {
                    $firstRow = is_array($resultData[0] ?? null)
                        ? $resultData[0]
                        : (array) ($resultData[0] ?? []);
                    $this->table(array_keys($firstRow), $rows);
                }
            }
        }

        $this->newLine();
        $this->info("Total cast votes: {$ballot->votes_count}");

        if ($ballot->quorum !== null) {
            $met = $ballot->votes_count >= $ballot->quorum ? 'Yes' : 'No';
            $this->info("Quorum: {$ballot->votes_count} / {$ballot->quorum} — Met: {$met}");
        }

        return 0;
    }
}
