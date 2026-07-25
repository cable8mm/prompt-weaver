<?php

use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;

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

    $prompt = $promptBuilder->build(Category::CAFE_RESTAURANT, Format::A45_POSTER);

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('You are a creative director for a Wi-Fi signage template.')
        ->toContain('Category: Cafe / Restaurant')
        ->toContain('Format: A4/A5 Poster Type')
        ->toContain('minimal Scandinavian, winter frost and pine mood, subtle grid pattern')
        ->toContain('"concept_name": "<short catchy concept name, 2-6 words>"')
        ->toContain('"design_brief": "<1-3 concise sentences')
        ->toContain('"color_direction": "<primary color palette description')
        ->toContain('"font_mood": "<short description of what typography feel fits')
        ->toContain('"design_brief" must be written in English')
        ->toContain('Output ONLY a valid JSON object');
});

it('uses the configured color in rule one', function () {
    $blackAndWhitePrompt = (new DesignBriefPrompt(product: 'a Wi-Fi signage template'))
        ->build(Category::OTHER, Format::CARD);
    $colorPrompt = (new DesignBriefPrompt(product: 'a Wi-Fi signage template', color: 'ocean blue and coral'))
        ->build(Category::OTHER, Format::CARD);

    expect($blackAndWhitePrompt)
        ->toContain('assume high-contrast monochrome/greyscale-safe design')
        ->not->toContain('use a color scheme based on ocean blue and coral');

    expect($colorPrompt)
        ->toContain('use a color scheme based on ocean blue and coral')
        ->not->toContain('assume high-contrast monochrome/greyscale-safe design');
});

it('does not immediately repeat a random seed within a pool', function () {
    $promptBuilder = new DesignBriefPrompt(product: 'a Wi-Fi signage template');

    $previous = null;

    foreach (range(1, 25) as $iteration) {
        $prompt = $promptBuilder->build(Category::OTHER, Format::CARD);
        preg_match('/Random creative seeds .*: (.+)/', $prompt, $matches);
        $current = $matches[1];

        expect($current)->not->toBe($previous);
        $previous = $current;
    }
});
