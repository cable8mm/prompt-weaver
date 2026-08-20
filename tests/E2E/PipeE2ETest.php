<?php

declare(strict_types=1);

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;

/*
|--------------------------------------------------------------------------
| End-to-End Tests (Real API Calls)
|--------------------------------------------------------------------------
| These tests make real API calls to OpenRouter and require:
|   - RUN_E2E_TESTS=1 environment variable
|   - tests/config.json with valid API keys (copy from tests/config.json.example)
|
| Run with:
|   RUN_E2E_TESTS=1 composer test:e2e
|
| These tests are opt-in and skipped by default. They make real API calls
| and may incur costs.
*/

// Load API keys from tests/config.json (gitignored) if it exists.
$configFile = __DIR__.'/../config.json';
if (file_exists($configFile)) {
    $config = json_decode(file_get_contents($configFile), true);
    if (is_array($config)) {
        foreach ($config as $key => $value) {
            putenv("{$key}={$value}");
        }
    }
}

uses()->group('e2e');

$skipE2E = ! getenv('RUN_E2E_TESTS');
$skipMsg = 'Set RUN_E2E_TESTS=1 and create tests/config.json with API keys to run e2e tests.';

it('runs the full pipeline with real OpenRouter API', function () {
    $client = new Client(
        provider: 'openrouter',
        apiKey: getenv('OPENROUTER_API_KEY') ?: null,
        model: 'google/gemma-4-26b-a4b-it:free',
    );

    $pipe = new Pipe($client);
    $result = $pipe->run(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
        color: 'warm brown and cream',
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
    file_put_contents($fixtureDir.'/config.json', json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);

    echo "Fixtures saved to: {$fixtureDir}\n\n";

    // Output all prompts and responses for debugging
    echo "=== DESIGN BRIEF PROMPT ===\n";
    echo $result->briefPrompt."\n\n";

    echo "=== DESIGN BRIEF RESPONSE ===\n";
    echo json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

    echo "=== CONFIG PROMPT ===\n";
    echo $result->configPrompt."\n\n";

    echo "=== CONFIG RESPONSE ===\n";
    echo json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

    echo "=== IMAGE PROMPT ===\n";
    echo $result->imagePrompt."\n\n";

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
