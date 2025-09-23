<?php

namespace App\Jobs;

use App\Services\DashboardAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class DashboardCacheJob implements ShouldQueue
{
    use Queueable , Dispatchable;

    /**
     * Create a new job instance.
     */

    protected $branch_id;
    public function __construct($branch_id)
    {
        $this->branch_id = $branch_id;
    }

    /**
     * Execute the job.
     */
    public function handle(DashboardAggregationService $dashboard_aggregation): void
    {
        $dashboard_aggregation->getCachedDashboardMetrics($this->branch_id);
    }
}
