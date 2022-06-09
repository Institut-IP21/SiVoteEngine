<?php

namespace App\Console\Commands;

use App\Models\Election;
use Illuminate\Console\Command;

class ElectionList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:list:election';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all elections';

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
        $elections = Election::all();
        $this->table(['ID', 'Title', 'Description', 'Level', 'Owner', 'Abstainable', 'Deleted At', 'Created At', 'Updated At'], $elections);
        return 0;
    }
}
