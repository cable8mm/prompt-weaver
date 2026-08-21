<?php

use Cable8mm\PromptWeaver\Laravel\PromptWeaverServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PromptWeaverServiceProvider::class,
            AiServiceProvider::class,
        ];
    }
}

uses(TestCase::class)->in(__DIR__);
