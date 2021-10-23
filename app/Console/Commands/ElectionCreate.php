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
                            {--T|title= : The title of the election}
                            {--D|description= : The description of the election?}
                            {--A|abstainable= : Whether voters can abstain on questions on this election}
                            {--L|level= : The security level of the election?}';

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
        $abstainable = strtolower($this->option('abstainable'));
        $level = intval($this->option('level'));
        $description = $this->option('description');

        if (!$title) {
            $title = $this->ask('Please provide a title for the election');
        }

        if (!$description) {
            $description = $this->ask('Optionally provide a description for the election', '');
        }

        if (!in_array($abstainable, ['yes', 'no'])) {
            $abstainable = $this->confirm('Can the voters abstain on questions in this election?', true);
        }

        if (!in_array($level, [2, 3])) {
            $levels = [
                2 => 'Level 2 - Medium level of security. Your organization only needs one voting committee to operate this election.',
                3 => 'Level 3 - High level of security. Your organization needs one voting committee to operate this election, as well operate the Proxy on your own infrastructure.'
            ];
            $level = $this->choice('What level of security should this election have?', $levels, 2);
            $level = array_search($level, $levels);
        }

        $election = Election::create([
            'title' => $title,
            'owner' => config('app.cli.default_owner'),
            'abstainable' => $abstainable === 'Yes',
            'level' => $level
        ]);

        $this->info("Created Election '{$election->title}' with id {$election->id}");
    }
}
