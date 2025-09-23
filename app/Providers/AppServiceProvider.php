<?php

namespace App\Providers;

use App\Services\InvoiceService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register() {
        $this->app->singleton(InvoiceService::class, function ($app) {
            return new InvoiceService();
        });
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
