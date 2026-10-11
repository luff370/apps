<?php

namespace App\Providers;

use App\Services\Tianji\ChatCompletionClient;
use App\Services\Tianji\DeepSeekChatClient;
use App\Services\Tianji\EloquentTianjiAgentCatalog;
use App\Services\Tianji\EloquentTianjiChatRepository;
use App\Services\Tianji\TianjiAgentCatalog;
use App\Services\Tianji\TianjiChatRepository;
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
        $this->app->bind(ChatCompletionClient::class, DeepSeekChatClient::class);
        $this->app->bind(TianjiChatRepository::class, EloquentTianjiChatRepository::class);
        $this->app->bind(TianjiAgentCatalog::class, EloquentTianjiAgentCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
