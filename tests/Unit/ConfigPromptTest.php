<?php

use Cable8mm\PromptWeaver\ConfigPrompt;

it('injects the design brief into the config prompt', function () {
    $designBrief = 'A cozy cafe Wi-Fi sign with warm brown and cream tones.';

    $prompt = (new ConfigPrompt)->build($designBrief);

    expect($prompt)
        ->toContain('[Role]')
        ->toContain('[Fixed schema')
        ->toContain('[User\'s design brief]')
        ->toContain($designBrief)
        ->toContain('"canvas"')
        ->toContain('"content"')
        ->toContain('"placeholders"')
        ->not->toContain('{$designBrief}');
});
