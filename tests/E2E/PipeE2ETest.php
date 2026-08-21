<?php

declare(strict_types=1);

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;
use Cable8mm\PromptWeaver\Support\Environment;

/*
|--------------------------------------------------------------------------
| End-to-End Tests (Real API Calls)
|--------------------------------------------------------------------------
| These tests make real API calls to OpenRouter and require:
|   - RUN_E2E_TESTS=1 environment variable
|   - .env with valid API keys and optional provider/model settings
|
| Run with:
|   RUN_E2E_TESTS=1 composer test:e2e
|
| These tests are opt-in and skipped by default. They make real API calls
| and may incur costs.
*/

// Load project-level environment settings, including API keys and model defaults.
Environment::load(dirname(__DIR__, 2).'/.env');

uses()->group('e2e');

$skipE2E = ! getenv('RUN_E2E_TESTS');
$skipMsg = 'Set RUN_E2E_TESTS=1 and configure API keys in .env to run e2e tests.';

it('runs the full pipeline with real OpenRouter API', function () {
    $client = app(AiClient::class);

    $pipe = new Pipe($client);
    $result = $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
        color: 'warm brown and cream',
        provider: getenv('PROMPT_WEAVER_PROVIDER') ?: 'openrouter',
        model: getenv('PROMPT_WEAVER_MODEL') ?: 'google/gemma-4-26b-a4b-it:free',
    );

    // Save generated working files outside the checked-in test fixtures.
    $fixtureDir = dirname(__DIR__, 2).'/.weaver/google-gemma-4-26b-a4b-it-free';

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    // Save manifest.json
    $manifest = [
        'code' => 'google-gemma-4-26b-a4b-it-free',
        'category' => 'Cafe/Restaurant',
        'format' => 'A4/A5 Poster',
    ];
    file_put_contents($fixtureDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

    // Save prompts
    file_put_contents($fixtureDir.'/brief.prompt', $result->briefPrompt.PHP_EOL);
    file_put_contents($fixtureDir.'/config.prompt', $result->configPrompt.PHP_EOL);
    file_put_contents($fixtureDir.'/image.prompt', $result->imagePrompt.PHP_EOL);

    // Save JSON responses
    file_put_contents($fixtureDir.'/design-brief.json', json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
    file_put_contents($fixtureDir.'/raw.config.json', json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);

    echo "Fixtures saved to: {$fixtureDir}\n\n";

    // Verify design brief response
    expect($result->briefJson)
        ->toHaveKey('description')
        ->and($result->briefJson['description'])
        ->not->toBeEmpty();

    // Verify config response structure
    expect($result->config)
        ->toHaveKey('canvas')
        ->toHaveKey('style')
        ->toHaveKey('content')
        ->toHaveKey('placeholders');

    // Verify canvas structure
    expect($result->config['canvas'])
        ->toHaveKey('aspect_ratio');

    // Verify style structure
    expect($result->config['style'])
        ->toHaveKey('theme')
        ->toHaveKey('background')
        ->toHaveKey('color_mode');

    // Verify content structure
    expect($result->config['content'])
        ->toHaveKey('title')
        ->toHaveKey('wifi_icon')
        ->toHaveKey('message')
        ->toHaveKey('footer');

    // Verify placeholders structure
    expect($result->config['placeholders'])
        ->toHaveKey('ssid')
        ->toHaveKey('password')
        ->toHaveKey('qr');

    // Verify image prompt is not empty
    expect($result->imagePrompt)
        ->not->toBeEmpty()
        ->and($result->imagePrompt)
        ->toContain('와이파이 연결');
})->skip($skipE2E, $skipMsg);
