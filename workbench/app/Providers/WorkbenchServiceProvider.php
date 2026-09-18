<?php

namespace Workbench\App\Providers;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Services\FixtureAiClient;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        if (! $this->app->runningUnitTests()) {
            $this->app->singleton(AiClient::class, FixtureAiClient::class);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__, 2).'/routes/web.php');
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
    }
}
