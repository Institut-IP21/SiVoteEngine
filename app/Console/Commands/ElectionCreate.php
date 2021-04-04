<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class ElectionCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:make:election
                            {--T|title= : The title of the election}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new election';

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
        $title = $this->option('title');

        if (!$title) {
            $title = $this->ask('Please provide a title for the election');
        }

        $election = Election::create([
            'title' => $title
        ]);

        $this->info("Created Election '{$election->title}' with id {$election->id}");
    }
}
