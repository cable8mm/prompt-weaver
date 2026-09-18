<?php

use Cable8mm\PromptWeaver\Laravel\PromptWeaverServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('p', 32)));
    }

    protected function getPackageProviders($app): array
    {
        return [
            PromptWeaverServiceProvider::class,
            AiServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }
}

uses(TestCase::class)->in(__DIR__);
