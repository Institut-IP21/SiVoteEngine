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
    public function handle(): int
    {
        // Do NOT use optimize:clear / cache:clear here: on the shared Redis host
        // our scoped ACL denies FLUSHDB (it would wipe every project's keys on
        // db0), and Laravel's Cache::flush() issues FLUSHDB. Clear only the
        // file-based caches instead — the redis app cache is keyed and expires
        // on its own, so it does not need flushing on deploy.
        $this->call('clear-compiled');
        $this->call('config:clear');
        $this->call('event:clear');
        $this->call('route:clear');
        $this->call('view:clear');

        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        $this->info('Cache cleared and created successfully!');
        return 0;
    }
}
