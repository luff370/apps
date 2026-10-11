<?php

namespace App\Providers;

use App\Services\Agent\ChatCompletionClient;
use App\Services\Agent\DeepSeekChatClient;
use App\Services\Agent\EloquentAgentCatalog;
use App\Services\Agent\EloquentAgentChatRepository;
use App\Services\Agent\AgentCatalog;
use App\Services\Agent\AgentChatRepository;
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
        $this->app->bind(AgentChatRepository::class, EloquentAgentChatRepository::class);
        $this->app->bind(AgentCatalog::class, EloquentAgentCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
