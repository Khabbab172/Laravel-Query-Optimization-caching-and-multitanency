<?php

namespace App\Console\Commands;

use App\Jobs\DashboardCacheJob;
use App\Models\Branch;
use Illuminate\Console\Command;

class CacheDashboard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache-dashboard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'this will cache data for every one minute to avoid cache-stampade';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $branches = Branch::all();
        foreach ($branches as $branch) {    
            DashboardCacheJob::dispatch($branch->id);
        }
    }
}
