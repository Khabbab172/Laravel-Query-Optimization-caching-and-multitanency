<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardAggregationService
{
    // A service method
//  we can cache data first for say 1 hour
// so that it first check if it has data in cache so fetch it from cache
// instead of querying data again
    public function getCachedDashboardMetrics($branch_id)
    {
        // Define a unique cache key for each branch and month
        $cachekey = "dashboard_metrics:branch:{$branch_id}:" . now()->format('Y-m');

        // Cache the data for 60 minutes (adjust as needed)
        return Cache::remember($cachekey, 3600, function () use ($branch_id) {
            return $this->getDashboardMetricsForBranch($branch_id);
        });
    }
    public function getDashboardMetricsForBranch($branch_id)
    {
        $current_month_start = Carbon::now()->startOfMonth();

        // I filter out the data on invoice table based on branch id
        // use aggregate function SUM for adding the monthly amount and no of unpaid monthly
        //  invoice .
        //  I added the CASE statement to filter out the paid and unpaid invoices.
        $revenue_data = DB::table("invoices")
            ->where("branch_id", $branch_id)
            ->select(
                DB::raw("SUM(CASE WHEN status='paid' AND created_at >= ? THEN amount ELSE 0 END) AS total_revenue_this_month"),
                DB::raw("SUM(CASE WHEN status='unpaid' AND created_at >= ? THEN 1 ELSE 0 END) AS total_unpaid_invoices"),
            )->setBindings([$current_month_start->toDateString()])
            ->first();


        // newly added users fetched based on branch id and those who are created this month only
        $new_users_this_month = DB::table("users")
            ->where("branch_id", $branch_id)
            ->where("created_at", ">=", $current_month_start->toDateString())
            ->count();


        // we join session_attendences with session table and group data by session status 
        // used aggregate function count for finding how many sessions attende or missed         
        $session_attences_breakdown = DB::table("sessions")
            ->join('session_attendances', 'sessions.id', '=', 'session_attendances.session_id')
            ->where("branch_id", $branch_id)
            ->groupBy('session_attendances.status')
            ->select(
                'session_attendances.status',
                DB::raw('count(*) as count'),
            )->pluck('count', 'status');




        return [
            'totalRevenueThisMonth' => $revenue_data->total_revenue_this_month,
            'totalUnpaidInvoices' => $revenue_data->total_unpaid_invoices,
            'newUsersThisMonth' => $new_users_this_month,
            'sessionAttendanceBreakdown' => $session_attences_breakdown,
        ];
    }




}