<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EvoteCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evote:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Caches everything it\'s supposed to';

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
        $this->call('optimize:clear');

        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        $this->info('Cache cleared and created successfully!');
        return 0;
    }
}
