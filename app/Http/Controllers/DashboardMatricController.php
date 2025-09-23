<?php

namespace App\Http\Controllers;

use App\Services\DashboardAggregationService;
use Illuminate\Http\Request;

class DashboardMatricController extends Controller
{
    protected  $dashboard_aggregation_service;

    public function __construct(DashboardAggregationService $dashboard_aggregation_service)

    {
        $this->dashboard_aggregation_service =  $dashboard_aggregation_service;
    }

    public function index(Request $request)
    {
        $branch_id = $request->input('branch_id');

        // fetching data for dashboard matric
        $dashboard_matric = $this->dashboard_aggregation_service->getCachedDashboardMetrics($branch_id);

        return response( ['dashboard_matric' => $dashboard_matric]);
    }
}
