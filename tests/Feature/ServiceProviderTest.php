<?php

use Cable8mm\PromptWeaver\Laravel\PromptWeaverServiceProvider;

it('registers the service provider in laravel application', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(PromptWeaverServiceProvider::class);
});

it('loads package json translations successfully', function () {
    app()->setLocale('ko');

    expect(__('Cafe / Restaurant'))->toBe('카페/레스토랑형');

    expect(__('A4/A5 Poster Type'))->toBe('A4/A5 포스터형');
});
