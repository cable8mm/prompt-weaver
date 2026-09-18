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

it('registers the Python environment commands', function () {
    expect(array_keys(Artisan::all()))
        ->toContain('prompt-weaver:install')
        ->toContain('prompt-weaver:doctor');
});

it('installs and checks the Python environment through uv', function () {
    $binary = tempnam(sys_get_temp_dir(), 'prompt-weaver-uv-');
    file_put_contents($binary, "#!/bin/sh\necho fake-uv\nexit 0\n");
    chmod($binary, 0755);
    config(['prompt-weaver.uv.binary' => $binary]);

    try {
        $this->artisan('prompt-weaver:install')
            ->expectsOutput('Prompt Weaver Python environment is ready.')
            ->assertExitCode(0);

        $this->artisan('prompt-weaver:doctor')
            ->expectsOutputToContain('[OK] uv')
            ->expectsOutputToContain('[OK] OpenCV')
            ->expectsOutput('Prompt Weaver Python runtime is ready.')
            ->assertExitCode(0);
    } finally {
        unlink($binary);
    }
});
