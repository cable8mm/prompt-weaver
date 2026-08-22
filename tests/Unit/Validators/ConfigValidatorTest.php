<?php

use Cable8mm\PromptWeaver\Validators\ConfigValidator;

it('accepts a config with the required top-level objects', function () {
    $config = [
        'canvas' => [
            'width_pc' => 100,
            'height_pc' => 100,
            'aspect_ratio' => '5:7',
        ],
        'style' => [
            'print_target' => 'black-and-white laser printer safe',
        ],
        'content' => [],
        'placeholders' => [],
    ];

    (new ConfigValidator)->validate($config);

    expect(true)->toBeTrue();
});

it('rejects a config with a missing top-level object', function () {
    (new ConfigValidator)->validate([
        'canvas' => ['aspect_ratio' => '5:7'],
        'style' => [],
        'content' => [],
    ]);
})->throws(RuntimeException::class, "Config is missing object 'placeholders'.");

it('rejects an invalid canvas aspect ratio', function () {
    (new ConfigValidator)->validate([
        'canvas' => ['aspect_ratio' => 'portrait'],
        'style' => [],
        'content' => [],
        'placeholders' => [],
    ], 'config.json');
})->throws(RuntimeException::class, 'Config has an invalid canvas.aspect_ratio: config.json');
