<?php

use Cable8mm\PromptWeaver\ImagePrompt;

function imagePromptConfig(string $aspectRatio): array
{
    return [
        'canvas' => ['aspect_ratio' => $aspectRatio],
        'style' => ['theme' => 'warm', 'background' => 'plain', 'print_target' => 'full-color inkjet printer'],
        'content' => [
            'message' => ['text' => '스캔하여 연결하세요.', 'x_pc' => 50, 'y_pc' => 80],
            'footer' => ['text' => '제작: WIFI NOTE', 'x_pc' => 50, 'y_pc' => 96],
        ],
        'placeholders' => [
            'ssid' => [
                'box_x_pc' => 50, 'box_y_pc' => 54, 'box_width_pc' => 80, 'box_height_pc' => 8,
                'label' => 'SSID:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid white',
            ],
            'password' => [
                'box_x_pc' => 50, 'box_y_pc' => 65, 'box_width_pc' => 80, 'box_height_pc' => 8,
                'label' => 'PASSWORD:', 'label_position' => 'outside_above', 'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid white',
            ],
            'qr' => ['x_pc' => 50, 'y_pc' => 27, 'width_pc' => 44, 'style' => 'square'],
        ],
    ];
}

it('instructs image generation to fill a square canvas without a nested page', function () {
    $prompt = new ImagePrompt(imagePromptConfig('1:1'));
    $prompt->build();

    expect($prompt->prompt())
        ->toContain('fill the entire square canvas edge-to-edge')
        ->toContain('Do not place the design on an inner sheet, portrait page, A4 paper')
        ->toContain('width=80% and height=8% of the full canvas exactly, from x=10% to x=90%');
});

it('keeps non-square canvas instructions portrait and full-bleed', function () {
    $prompt = new ImagePrompt(imagePromptConfig('5:7'));
    $prompt->build();

    expect($prompt->prompt())
        ->toContain('Portrait canvas, aspect ratio 5:7')
        ->toContain('The artwork must fill the entire canvas');
});
