<?php

namespace Cable8mm\PromptWeaver;

use Illuminate\Support\ServiceProvider;

class PromptWeaverServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'prompt-weaver');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/prompt-weaver'),
            ], 'prompt-weaver-translations');
        }
    }
}
