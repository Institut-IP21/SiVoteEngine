<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\BallotComponent;

use Illuminate\Console\Command;

class BallotComponentList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:list:ballot:component
                            {--B|ballot= : Only display ballots from specified ballot}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all ballots';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ballotId = $this->option('ballot');

        if ($ballotId && Ballot::where('id', $ballotId)->exists()) {
            $ballotComponents = BallotComponent::where('ballot_id', $ballotId)->get();
            $this->info("Displaying Component of Ballot $ballotId");
        } else {
            $ballotComponents = BallotComponent::all();
            $this->info("Displaying all Components");
        }
        $this->newLine();

        $rows = $ballotComponents->map(function (BallotComponent $c) {
            return [
                $c->id,
                $c->ballot_id,
                $c->title,
                $c->type,
                $c->deleted_at,
                $c->version,
                $c->created_at,
                $c->updated_at,
            ];
        });

        $this->table(['ID', 'Ballot', 'Title', 'Type', 'Deleted At', 'version', 'Created At', 'Updated At'], $rows);
        return 0;
    }
}
