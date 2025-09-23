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
    /**
     * Create a new job instance.
     */
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
