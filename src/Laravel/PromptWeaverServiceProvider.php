<?php

namespace Cable8mm\PromptWeaver\Laravel;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Cable8mm\PromptWeaver\Support\PromptWeaverLogger;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class PromptWeaverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/prompt-weaver.php', 'prompt-weaver');

        $this->app->singleton(LoggerInterface::class, function ($app): LoggerInterface {
            if (! $app->bound('prompt-weaver.standalone') && $app->bound('log')) {
                return $app->make('log');
            }

            return PromptWeaverLogger::standalone(
                (string) config('prompt-weaver.logging.path')
            );
        });

        $this->app->singleton(AiClient::class, LaravelAiClient::class);
    }

    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../../lang');

        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'prompt-weaver');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../lang' => $this->app->langPath('vendor/cable8mm/prompt-weaver'),
            ], 'prompt-weaver-translations');

            $this->publishes([
                __DIR__.'/../../config/prompt-weaver.php' => config_path('prompt-weaver.php'),
            ], 'prompt-weaver-config');
        }
    }
}
