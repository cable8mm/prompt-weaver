<?php

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;

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
        ->toContain('[Fixed schema')
        ->toContain($description)
        ->toContain($colorDirection)
        ->toContain($fontMood)
        ->toContain($name)
        ->toContain('"canvas"')
        ->toContain('"aspect_ratio": "5:7"')
        ->toContain('"color_mode": "mono"')
        ->toContain('"content"')
        ->toContain('"placeholders"')
        ->not->toContain('{$description}');
});
