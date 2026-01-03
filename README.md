# Laravel query optimization ,caching and multitanency demonstration

## Part 1 : Query Optimization
A common query in our system is slowing down as data grows:
```php 
DB::table('users')
->join('form_data', 'form_data.user_id', '=', 'users.id')
->join('form_options', 'form_data.option_id', '=', 'form_options.id')
->where('users.tenant_id', $tenantId)
->where('form_options.label', 'like', '%keyword%')
->select('users.name', 'form_options.label')
->paginate(50);
```

### Tasks:
1. Rewrite the query using Eloquent or Query Builder with better performance.
2. Suggest appropriate indexes for this setup.
3. Recommend scalable alternatives:
- Search engines like Meilisearch/Elastic
- Denormalized structures
4. Suggest caching strategies if this query is used in a paginated admin UI.

### Solution : 
```php 
    // you can find this code \app\Http\Controllers\UserSearchController.php
    FacadesCache::remember($cache_key, 300, function ()  use ($keyword, $tenant_id) {

            return User::with('formData.option')   // here I use eager loading for fetching relations upfront 
                // instead of multiple queries  
                ->where('tenant_id', $tenant_id)  // fetch first the users with given tenant id , then....
                ->whereHas('formData.option', function ($query) use ($keyword) {  // ...add a relationship count or exists condition to the 
                    // query with where clauses. in conjunction 
                    // with the MATCH(label) against given keyword condition 
                    $query->whereRaw("MATCH(label) AGAINST(? IN NATURAL LANGUAGE MODE)", [$keyword]);  // I added fulltext index on label column in form_options table 
                    // works like an inverted index ex word => [row1 , row2 , row3 ]
                })->orderBy("id")  // I order the results by Id 
                ->paginate(50); // paginate to return 50 results per page
        });
        // I would like to recommend the meilisearch using laravel scout package for supporting fulltext search .
        //  for denormalizing data we can directly store labels in users table in seprate column and add full text index on it , 
        // with tradeoff of xtra storage
        // lastly we can cache the data for perticular time using Cache facade remember method

        //  this controller is called in  \routes\api.php
```


## Part 2: Queue Race Conditions
We have jobs that update user balances when invoices are created, updated, or deleted.
Occasionally, due to multiple Laravel servers, these jobs are processed out of order,
resulting in incorrect balances.
Tasks:
1. Describe the race condition and propose two solutions to ensure correct event order
per user.
2. Show code/config on how to implement Redis Stream partitioning or Kafka-style
per-user queuing in Laravel.
3. How would you handle cases where multiple concurrent actions happen on the same
user?

## solution :
### Race conditions
-- If we enqueue jobs that mutate a user’s balance when invoices are created/updated/deleted .because we have multiple workers/servers jobs for the same user 
   can be processed in different orders (A then B vs B then A). If job B expects the action from job A to have already been applied, processing them out-of-order
   results in incorrect balances.
-- the root cause is lack of ordering gaurantee for jobs that are associated to logically same entity .
-- example :-
invoice created job dispatched to add 10 to user's balance
currently user have 100
an invoice is deleted successfully and subtract 20 the balance will temporarily become 80
then 10 is added to 100 (old value) completely ignoring the previous opration 
but correct flow is 100 + 10 -> 110 - 20 -> 90

so the problem is that we lack of ordering the jobs execution 
we need to make sure that the job 1 completed only then we can go for job 2.



### To rsolve this issue we have two options :-
-- Radis queue partitions :- for each user we can have seprate dedicated partition there job belongs to a user processed sequentially with in that partion.
-- Kafka style keyed messages :- the idea is to use a brocker that gaurantees ordering by key , all message with same key processed in same partition and consumed in order.
    -- producer :- publish event with a key
    -- consumer :- run consumers in a consumer group Kafka ensures messages with same key always go to same partition and are processed in order by a single consumer thread for that partition.

I will go with redis queue partitions per user 
fisrt we need to add the redis driver configuration 
```php 
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'default',
        'retry_after' => 90,
        'block_for' => 5,
    ],
],
```


we need to dispatch the jobs to specific user for that we can define onQueue method
take user id as key 



```php 
// \app\Jobs\UpdateBalanceJob.php
<?php

namespace App\Jobs;


use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateBalanceJob implements ShouldQueue
{
    use Queueable , Dispatchable , InteractsWithQueue,SerializesModels ;
    protected $amount;
    protected $user_id;
    public function __construct($user_id , $amount){
        $this->user_id = $user_id;
        $this->amount = $amount;
    }
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // It could be possible that a user fetch user balance and
        // before he make change another user update it ,
        // to avoid this we acquire a lock , and make these series of steps a
        //  single logical using by using transaction 
        DB::transaction(function () {  
            $user = User::lockForUpdate()->find($this->user_id); // Acquire an exclusive lock
            $user->balance += $this->amount;
            $user->save();
        });
    }

    // This is the key part to partition the queue
    public function onQueue()
    {
        return 'user_balance_' . $this->user_id; // this is the key for partitioning 
    }

    // here we dispatching job for user with id 123
    // UpadateBalanceJob::dispatch(123, 40)->onQueue('user_balance_123');
    // UpadateBalanceJob::dispatch(123, -20)->onQueue('user_balance_123');

    // here we dispatching job for user with id 456
    // UpadateBalanceJob::dispatch(123, 40)->onQueue('user_balance_456');
    // UpadateBalanceJob::dispatch(123, -20)->onQueue('user_balance_456');

    // to process these queues need to run :-
    // php artisan --queue=user_balance_123 --daemon 

}
```
## Part 3: Multi-Tenant Data Isolation
We isolate all data in the app using tenant_id.
Tasks:
1. Write a base Eloquent scope or trait to auto-apply tenant_id in all queries.
2. Explain how you’d enforce tenant isolation at:
- Controller/service layer
- (Optional) Database level
3. How would you test for tenant data leaks?


we first add TenantScope in our app it adds a where clause to access concerned tenant data 
leter we apply this scope globally in our app to make all our db interactions tenant aware and isolated 
from each other
```php 
// \app\Models\Scopes\TenantScope.php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user(); // get authenticated user
        //  adding where clause for identifiying tenant data in whole 
        // later in Tenanted trait we add this scope globally 
        if ($user && $user->tenant_id) {
            $builder->where("tenant_id", $user->tenant_id); 
        }
    }
}
```

we use Tenanted trait , adding tenant_id before the model gets booted
from 


```php 
// \app\Models\Traits\Tenanted.php
<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait Tenanted
{
    //  The booting method of trait automatically called when eloquent model boots
    protected static function bootTenanted(){
        // Set the tenant_id before model gets created
        static::creating(function ($model) {
            $tenant_id = Auth::user()->tenant_id ?? null;

            // if a model does not already have tenant id we set it manually to avoid overwritting manually set id
            if(is_null($model->tenant_id)){
                $model->tenant_id = $tenant_id;
            }

        });

        // applying the global scope for all models , this add a where clause for tenant_id 
        //  so from each table it access only concerned tenant data  

        static::addGlobalScope(new TenantScope) ;
    }
}

```

additionally we can store the tenant id in session or configuration
using middleware 

```php 
// app/Http/Middleware/SetTenantIdMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if a user is authenticated
        if (Auth::check()) {
            // Retrieve the authenticated user's tenant_id and store it.
            $tenantId = Auth::user()->tenant_id;

            // Store the tenant ID in the session
            session(['tenant_id' => $tenantId]);

            // Example: Set a global configuration value
            config(['app.tenant_id' => $tenantId]);
        }

        return $next($request);
    }
}

```

Instead of directly calling eloquent models inside the controllers I add services to seprate out 
the buisness logic , the service layer is responsible for data retrieval and modifications

```php
// \app\Services\InvoiceService.php
<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceService
{
    // A service method
    public function getInvoices()
    {
        // The Eloquent model will automatically apply the tenant scope.
        return Invoice::all();
    }

}

<?php
// \app\Http\Controllers\InvoiceController.php
namespace App\Http\Controllers;

use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected  $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService =  $invoiceService;
    }

    public function index()
    {
        // The controller is not concerned with the tenant_id.
        $invoices = $this->invoiceService->getInvoices();

        return response( ['invoices' => $invoices]);
    }

}


```

we write phpunit feature test for testing if a user associted to given tenant
can only see his own invoices 

```php
// \tests\Feature\MultitenantTest.php
<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MultitenantTest extends TestCase
{

    use RefreshDatabase;
    
    /**
     * A basic feature test example.
     */

    // Properties to hold our test data
    protected $tenantA;
    protected $userA;
    protected $tenantB;
    protected $userB;
    protected $invoiceA1;
    protected $invoiceA2;
    protected $invoiceB1;

    public function setUp(): void{
        parent::setUp();
        // add tenant A and user A
        $this->tenantA = Tenant::factory()->create();
        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        // add tenant B and user B
        $this->tenantB = Tenant::factory()->create();
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        // added invoice A1 and A2  for tenant A and Invoice B1 for tenant B
        $this->invoiceA1 = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->invoiceA2 = Invoice::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->invoiceB1 = Invoice::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // test that user can see their own invoices
    public function test_a_user_can_only_see_their_own_invoices(): void
    {
        // 3. Execute Queries and Assertions
        $this->actingAs($this->userA);

        // Get all invoices for the authenticated user
        $invoices = Invoice::all();

        // Assert that the user can only see their two invoices
        // we get invoices of authenticated user A associated to tenant A 
        // which has two invoices A1 and A2 
        // so we xpect 2 records here 
        // check if the invoice contain A1
        // check if the invoice not contai B1 (tenant B)
        $this->assertCount(2, $invoices);
        $this->assertTrue($invoices->contains($this->invoiceA1));
        $this->assertFalse($invoices->contains($this->invoiceB1));
    }

}

```


## Part 4: Fast Dashboard Aggregation
We want to render a per-branch dashboard with the following metrics:
- Total revenue this month
- Total unpaid invoices
- New users this month
- Session attendance breakdown
Tasks:
1. Write an optimized query or set of queries using Eloquent/Query Builder to generate
this dashboard data.
2. Propose a caching strategy.
3. Suggest a solution to support 100+ branches accessing this concurrently.

```php 

<?php
// \app\Services\DashboardAggregationService.php
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
```


to handle the situation where 100+ branches access the dashboard concurrently 
the load on database would be immence multiple request concurrently querying databse 
to avoid this we can avoid this using cache preventing them querying the database if we have data in cache , 

but there is a chance when multiple requst access the dashboard the same time when cache expires 

to avoid it we can create a job to cache the admin dashboard data

```php
// \app\Jobs\DashboardCacheJob.php
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


```
Schedule a command to dispatch this job

```php
// \app\Console\Commands\CacheDashboard.php
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


```


## Bonus – Laravel and Infrastructure Notes

### Migration / Infrastructure Notes
- Laravel Version Upgrade: Laravel 8 → 9
  - Updated dependencies and ensured PHP 8 compatibility.

- MySQL Optimizations:
  - Added composite indexes (tenant_id, user_id) on form_data.
  - Created FULLTEXT indexes on form_options.label for keyword search.
  - Tuned innodb_buffer_pool_size for faster reads.

- Multi-Region Considerations:
  - Multiple Laravel servers behind a load balancer.
  - Read replicas in MySQL for region-local reads.
  - Central Redis cluster with Sentinel for failover.
  - Queue partitioning by region and user for concurrency safety.
