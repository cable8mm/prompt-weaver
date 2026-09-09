<?php

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Image;
use Laravel\Ai\StructuredAnonymousAgent;
use Psr\Log\LoggerInterface;

it('uses the host Laravel logger when available', function () {
    expect(app(LoggerInterface::class))->toBe(app('log'));
});

it('uses Laravel AI structured output for text responses', function () {
    StructuredAnonymousAgent::fake([
        ['name' => '테스트 템플릿'],
    ]);

    $result = app(AiClient::class)->structured(
        'Return a template name.',
        fn ($schema): array => [
            'name' => $schema->string()->required(),
        ],
        provider: 'openai',
        model: 'test-model',
    );

    expect($result)->toBe(['name' => '테스트 템플릿']);
    StructuredAnonymousAgent::assertPrompted('Return a template name.');
});

it('retries transient structured provider failures with the configured timeout', function () {
    config([
        'prompt-weaver.ai.timeout' => 7,
        'prompt-weaver.ai.retries' => 1,
        'prompt-weaver.ai.retry_sleep_ms' => 0,
    ]);

    $attempts = 0;
    StructuredAnonymousAgent::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw ProviderConnectionException::forProvider('openrouter');
        }

        return ['name' => '재시도 성공'];
    });

    $result = app(AiClient::class)->structured(
        'Return a template name.',
        fn ($schema): array => [
            'name' => $schema->string()->required(),
        ],
        provider: 'openrouter',
        model: 'test-model',
    );

    expect($result)->toBe(['name' => '재시도 성공'])
        ->and($attempts)->toBe(2);
});

it('returns binary contents from Laravel AI image responses', function () {
    Image::fake([base64_encode('fake-image')]);

    $result = app(AiClient::class)->image(
        'Generate a Wi-Fi sign.',
        provider: 'openai',
        model: 'test-image-model',
    );

    expect($result)->toBe('fake-image');
    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('Generate a Wi-Fi sign.'));
});
