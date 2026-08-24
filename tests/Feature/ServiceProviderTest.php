<?php

use Cable8mm\PromptWeaver\Laravel\PromptWeaverServiceProvider;

it('registers the service provider in laravel application', function () {
    expect(app()->getLoadedProviders())
        ->toHaveKey(PromptWeaverServiceProvider::class);
});

it('loads package json translations successfully', function () {
    app()->setLocale('ko');

    expect(__('Cafe/Restaurant'))->toBe('카페/레스토랑형');

    expect(__('A4/A5 Poster'))->toBe('A4/A5 포스터형');
});

it('provides a Vite-compatible browser stylesheet', function () {
    $stylesheet = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/prompt-weaver.css');

    expect($stylesheet)
        ->toContain('../../fonts/AtkinsonHyperlegible-Regular.woff2')
        ->toContain('../../fonts/AtkinsonHyperlegible-Bold.woff2')
        ->not->toContain('/vendor/prompt-weaver/fonts/');
});
