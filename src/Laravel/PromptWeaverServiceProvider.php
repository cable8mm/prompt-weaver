<?php

namespace Cable8mm\PromptWeaver\Laravel;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Illuminate\Support\ServiceProvider;

class PromptWeaverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/prompt-weaver.php', 'prompt-weaver');

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
