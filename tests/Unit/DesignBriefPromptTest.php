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

it('builds a Wi-Fi signage design brief prompt using the category and format', function () {
    $promptBuilder = new DesignBriefPrompt(
        category: Category::CAFE_RESTAURANT,
        format: Format::A45_POSTER,
    );

    set_private_property($promptBuilder, 'moodSeeds', ['minimal Scandinavian']);
    set_private_property($promptBuilder, 'seasonSeeds', ['winter frost and pine mood']);
    set_private_property($promptBuilder, 'textureSeeds', ['subtle grid pattern']);
    set_private_property($promptBuilder, 'layoutSeeds', ['balanced asymmetrical composition']);
    set_private_property($promptBuilder, 'motifSeeds', ['soft architectural arches']);
    set_private_property($promptBuilder, 'materialSeeds', ['matte paper stock']);
    set_private_property($promptBuilder, 'contrastSeeds', ['high-contrast graphic treatment']);

    $promptBuilder->build();
    $prompt = $promptBuilder->prompt();

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('You are a creative director for a Wi-Fi signage template.')
        ->toContain('Category: Cafe/Restaurant')
        ->toContain('Format: A4/A5 Poster')
        ->toContain('minimal Scandinavian, winter frost and pine mood, subtle grid pattern, balanced asymmetrical composition, soft architectural arches, matte paper stock, high-contrast graphic treatment')
        ->toContain('"name": "<short template name, 2-6 words>"')
        ->toContain('"description": "<시각적 테마, 배경 스타일, 분위기를 설명하는 1-3개의 간결한 문장>')
        ->toContain('"color_direction": "<주요 색상 팔레트 설명')
        ->toContain('"font_mood": "<어울리는 글꼴 분위기에 대한 짧은 설명')
        ->toContain('"description", "color_direction", and "font_mood" must be written in Korean')
        ->toContain('Output ONLY a valid JSON object');
});

it('uses the configured color in rule one', function () {
    $blackAndWhitePrompt = new DesignBriefPrompt(
        category: Category::OTHER,
        format: Format::A45_POSTER,
    );
    $blackAndWhitePrompt->build();
    $blackAndWhiteText = $blackAndWhitePrompt->prompt();

    $colorPrompt = new DesignBriefPrompt(
        category: Category::OTHER,
        format: Format::A45_POSTER,
        color: 'ocean blue and coral',
    );
    $colorPrompt->build();
    $colorText = $colorPrompt->prompt();

    expect($blackAndWhiteText)
        ->toContain('assume high-contrast monochrome/greyscale-safe design')
        ->not->toContain('use a color scheme based on ocean blue and coral');

    expect($colorText)
        ->toContain('use a color scheme based on ocean blue and coral')
        ->not->toContain('assume high-contrast monochrome/greyscale-safe design');
});

it('does not immediately repeat a random seed within a pool', function () {
    $promptBuilder = new DesignBriefPrompt(
        category: Category::OTHER,
        format: Format::A45_POSTER,
    );

    $previous = null;

    foreach (range(1, 25) as $iteration) {
        $promptBuilder->build();
        $prompt = $promptBuilder->prompt();
        preg_match('/Random creative seeds .*: (.+)/', $prompt, $matches);
        $current = $matches[1];

        expect($current)->not->toBe($previous);
        $previous = $current;
    }
});
