<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\Election;

use Illuminate\Console\Command;

class BallotList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:list:ballot
                            {--E|election= : Only display ballots from specified election}';

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
        $electionId = $this->option('election');

        if ($electionId && Election::exists($electionId)) {
            $ballots = Ballot::find(['election_id' => $electionId]);
            $this->info("Displaying Ballots for Election $electionId");
        } else {
            $ballots = Ballot::all();
            $this->info("Displaying all Ballots");
        }
        $this->newLine();
        $this->table(['ID', 'Title', 'Election ID', 'Active', 'Deleted At', 'Created At', 'Updated At'], $ballots);
    }
}
