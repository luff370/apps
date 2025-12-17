<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\Services\GroupDataService;
use App\Support\Services\SystemConfigService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('sysConfig', function ($app) {
            return new SystemConfigService;
        });
        $this->app->singleton('sysGroupData', function ($app) {
            return new GroupDataService;
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
