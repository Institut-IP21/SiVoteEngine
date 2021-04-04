<?php

namespace App\Console\Commands;

use App\Models\Ballot;
use App\Models\Election;
use Illuminate\Console\Command;

class BallotCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:make:ballot
                            {--E|election= : The election ID}
                            {--T|title= : The title of the ballot}
                            {--D|description= : The description of the ballot component}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new ballot for a specified election';

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
        $title = $this->option('title');
        $description = $this->option('description');

        while (!$electionId || !Election::exists($electionId)) {
            $electionId = $this->ask('Please enter the ID of an existing election');
            $electionExists = Election::exists($electionId);
            if (!$electionExists) {
                $this->info("Could not find election with ID {$electionId}");
            }
        }

        while (!$title) {
            $title = $this->ask('Please enter a title for the ballot');
        }

        while (!$description) {
            $description = $this->ask('Please enter a description for the ballot');
        }

        $ballot = Ballot::create([
            'election_id' => $electionId,
            'title' => $title,
            'description' => $description,
        ]);

        $this->info("Created new ballot titled '{$ballot->title}' with ID {$ballot->id} for election '{$ballot->election->title}'.");
    }
}
