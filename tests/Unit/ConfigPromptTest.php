<?php

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;

it('injects the design brief, color direction, and font mood into the config prompt', function () {
    $description = 'A cozy cafe Wi-Fi sign with warm brown and cream tones.';
    $colorDirection = 'warm brown and cream tones with soft gold accents';
    $fontMood = 'rounded handwritten-style Korean font';
    $name = '카페 시그니처';

    $configPrompt = new ConfigPrompt(
        description: $description,
        colorDirection: $colorDirection,
        fontMood: $fontMood,
        format: Format::A45_POSTER,
        name: $name,
        colorMode: ColorMode::MONO,
    );
    $configPrompt->build();
    $prompt = $configPrompt->prompt();

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('[Schema]')
        ->toContain($description)
        ->toContain($colorDirection)
        ->toContain($fontMood)
        ->toContain($name)
        ->toContain('"canvas"')
        ->toContain('"aspect_ratio": "5:7"')
        ->toContain('"color_mode": "mono"')
        ->toContain('"content"')
        ->toContain('"placeholders"')
        ->toContain('balanced centered composition')
        ->not->toContain('{$description}');
});

it('loads the selected layout preset into the config prompt', function () {
    $configPrompt = new ConfigPrompt(
        description: 'A bold sign.',
        colorDirection: 'black and white',
        fontMood: 'strong geometric type',
        format: Format::A45_POSTER,
        layout: Layout::QR_FOCUS,
    );
    $configPrompt->build();

    expect($configPrompt->prompt())
        ->toContain('Make the QR code the primary visual anchor')
        ->toContain('"box_y_pc": 68');
});
