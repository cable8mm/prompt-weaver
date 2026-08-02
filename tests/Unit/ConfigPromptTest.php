<?php

use Cable8mm\PromptWeaver\ConfigPrompt;

it('injects the design brief, color direction, and font mood into the config prompt', function () {
    $designBrief = 'A cozy cafe Wi-Fi sign with warm brown and cream tones.';
    $colorDirection = 'warm brown and cream tones with soft gold accents';
    $fontMood = 'rounded handwritten-style Korean font';
    $conceptName = '카페 시그니처';

    $configPrompt = new ConfigPrompt(
        designBrief: $designBrief,
        colorDirection: $colorDirection,
        fontMood: $fontMood,
        conceptName: $conceptName,
    );
    $configPrompt->build();
    $prompt = $configPrompt->prompt();

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('[Fixed schema')
        ->toContain($designBrief)
        ->toContain($colorDirection)
        ->toContain($fontMood)
        ->toContain($conceptName)
        ->toContain('"canvas"')
        ->toContain('"content"')
        ->toContain('"placeholders"')
        ->not->toContain('{$designBrief}');
});
