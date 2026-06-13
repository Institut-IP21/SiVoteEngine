<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\Vote;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BallotCodesGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:make:ballot:codes
                            {--B|ballot= : The ID of a the ballot}
                            {--Q|quantity= : The number of codes to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a specified number of unique codes for a ballot';

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
    public function handle(): int
    {
        $ballotId = $this->option('ballot');

        while (!$ballotId || !Ballot::where('id', $ballotId)->exists()) {
            $ballotId = $this->ask('Please enter the ID of an existing ballot');
            if (!$ballotId || !Ballot::where('id', $ballotId)->exists()) {
                $this->error("Could not find Ballot with ID $ballotId");
            }
        }

        $quantity = (int) $this->option('quantity');
        while (!$quantity || $quantity < 1) {
            $quantity = (int) $this->ask('Please enter the number of codes to generate');
        }

        $codes = [];
        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < $quantity; $i++) {
            $vote = Vote::create(['ballot_id' => $ballotId, 'created_at' => $now]);
            $codes[] = $vote->id;
        }

        $this->info(print_r($codes, true));
        return 0;
    }
}
