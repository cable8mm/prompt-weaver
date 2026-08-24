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
        'placeholders' => [
            'ssid' => ['font_size_px' => 36],
            'password' => ['font_size_px' => 36],
        ],
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

it('accepts physical typography with print canvas metadata', function () {
    (new ConfigValidator)->validate([
        'canvas' => ['aspect_ratio' => '5:7', 'width_mm' => 210, 'height_mm' => 297, 'dpi' => 300],
        'style' => ['print_target' => 'black-and-white laser printer safe'],
        'content' => [],
        'placeholders' => [
            'ssid' => ['font_size_pt' => 18],
            'password' => ['font_size_pt' => 18],
        ],
    ]);

    expect(true)->toBeTrue();
});

it('rejects a config without a valid font size', function () {
    (new ConfigValidator)->validate([
        'canvas' => ['aspect_ratio' => '5:7'],
        'style' => ['print_target' => 'black-and-white laser printer safe'],
        'content' => [],
        'placeholders' => ['ssid' => [], 'password' => []],
    ]);
})->throws(RuntimeException::class, 'missing font_size_pt or font_size_px');

it('rejects a physical font size outside the print range', function () {
    (new ConfigValidator)->validate([
        'canvas' => ['aspect_ratio' => '5:7', 'width_mm' => 210, 'height_mm' => 297, 'dpi' => 300],
        'style' => ['print_target' => 'black-and-white laser printer safe'],
        'content' => [],
        'placeholders' => ['ssid' => ['font_size_pt' => 4], 'password' => ['font_size_pt' => 18]],
    ]);
})->throws(RuntimeException::class, 'invalid font_size_pt');
