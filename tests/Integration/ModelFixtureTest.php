<?php

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\ImagePrompt;

function fixture_json(string $relativePath): array
{
    $path = __DIR__.'/../Fixtures/'.$relativePath;
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse();

    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    expect($decoded)->toBeArray();

    return $decoded;
}

it('replays the gpt-54-mini fixture chain', function () {
    $manifest = fixture_json('gpt-54-mini/wifi-note-cafe/manifest.json');
    $designBrief = fixture_json('gpt-54-mini/wifi-note-cafe/design-brief.json');
    $config = fixture_json('gpt-54-mini/wifi-note-cafe/config.json');
    $imageFixturePath = __DIR__.'/../Fixtures/gpt-54-mini/wifi-note-cafe/image.txt';
    $imageFixture = file_get_contents($imageFixturePath);

    expect($imageFixture)->not->toBeFalse();

    $designBriefPrompt = new DesignBriefPrompt(product: $manifest['product']);
    $configPrompt = new ConfigPrompt;
    $imagePrompt = new ImagePrompt;

    $briefPromptText = $designBriefPrompt->build($manifest['category'], $manifest['format']);
    $configPromptText = $configPrompt->build($designBrief['design_brief']);
    $imagePromptText = $imagePrompt->build($config);

    expect($manifest)
        ->toMatchArray([
            'model' => 'gpt-54-mini',
            'scenario' => 'wifi-note-cafe',
            'product' => 'a Wi-Fi signage template',
            'category' => 'Cafe/Restaurant',
            'format' => 'A4/A5 Poster',
        ]);

    expect($designBrief)
        ->toMatchArray([
            'concept_name' => '따뜻한 와이파이 코너',
            'color_direction' => 'Cream, coffee brown, and charcoal with subtle beige accents.',
            'font_mood' => 'Rounded handwritten Korean typography with a friendly bold weight.',
        ])
        ->and($designBrief['design_brief'])
        ->toContain('cozy cafe-style Wi-Fi sign')
        ->and($designBrief['design_brief'])
        ->toContain('print-safe');

    expect($briefPromptText)
        ->toContain($manifest['product'])
        ->toContain($manifest['category'])
        ->toContain($manifest['format']);

    expect($configPromptText)
        ->toContain($designBrief['design_brief'])
        ->toContain('[Fixed schema')
        ->toContain('[User\'s design brief]');

    expect($config['canvas'])
        ->toMatchArray([
            'aspect_ratio' => '3:4',
        ]);

    expect($config['style'])
        ->toMatchArray([
            'print_target' => 'black-and-white laser printer safe',
        ]);

    expect($imagePromptText)
        ->toBe(trim($imageFixture))
        ->toContain($config['style']['theme'])
        ->toContain($config['content']['title']['text'])
        ->toContain($config['placeholders']['qr']['style'])
        ->toContain('Output the image only.');
});
