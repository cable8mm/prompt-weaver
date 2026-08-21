<?php

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Laravel\Ai\Image;
use Laravel\Ai\StructuredAnonymousAgent;

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
