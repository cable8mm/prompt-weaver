<?php

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;

/**
 * Load E2E test configuration from tests/config.json
 *
 * @return array{OPENROUTER_API_KEY: string}
 *
 * @throws RuntimeException If config file is not found
 */
function loadE2EConfig(): array
{
    $configPath = __DIR__.'/../config.json';

    if (! file_exists($configPath)) {
        throw new RuntimeException("E2E config not found: {$configPath}. Please create it from tests/config.json.example");
    }

    $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

    if (! isset($config['OPENROUTER_API_KEY']) || empty($config['OPENROUTER_API_KEY'])) {
        throw new RuntimeException('OPENROUTER_API_KEY is not set in tests/config.json');
    }

    return $config;
}

it('runs the full pipeline with real OpenRouter API', function () {
    $config = loadE2EConfig();

    $client = new Client(
        provider: 'openrouter',
        apiKey: $config['OPENROUTER_API_KEY'],
        model: 'google/gemma-4-26b-a4b-it:free',
    );

    $pipe = new Pipe($client);
    $result = $pipe->run(
        product: 'a Wi-Fi signage template',
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
        color: 'warm brown and cream',
    );

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
        ->toHaveKey('design_brief')
        ->and($result->briefJson['design_brief'])
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
        ->toHaveKey('print_target');

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
})->group('e2e');
