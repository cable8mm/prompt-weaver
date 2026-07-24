<?php

use Cable8mm\PromptWeaver\DesignBriefPrompt;

function set_private_property(object $object, string $property, array $value): void
{
    $reflection = new ReflectionProperty($object, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($object, $value);
}

it('builds a design brief prompt using the provided product, category, and format', function () {
    $promptBuilder = new DesignBriefPrompt(product: 'a Wi-Fi signage template');

    set_private_property($promptBuilder, 'moodSeeds', ['minimal Scandinavian']);
    set_private_property($promptBuilder, 'seasonSeeds', ['winter frost and pine mood']);
    set_private_property($promptBuilder, 'textureSeeds', ['subtle grid pattern']);

    $prompt = $promptBuilder->build('Cafe/Restaurant', 'A4/A5 Poster');

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('You are a creative director for a Wi-Fi signage template.')
        ->toContain('Category: Cafe/Restaurant')
        ->toContain('Format: A4/A5 Poster')
        ->toContain('minimal Scandinavian, winter frost and pine mood, subtle grid pattern')
        ->toContain('"concept_name": "<short catchy concept name, 2-6 words>"')
        ->toContain('"design_brief": "<1-3 concise sentences')
        ->toContain('"color_direction": "<primary color palette description')
        ->toContain('"font_mood": "<short description of what typography feel fits')
        ->toContain('"design_brief" must be written in English')
        ->toContain('Output ONLY a valid JSON object');
});
