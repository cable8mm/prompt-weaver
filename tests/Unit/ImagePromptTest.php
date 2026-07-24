<?php

use Cable8mm\PromptWeaver\ImagePrompt;

it('builds a detailed image prompt from the structured config', function () {
    $prompt = (new ImagePrompt())->build([
        'canvas' => [
            'aspect_ratio' => '3:4',
        ],
        'style' => [
            'theme' => 'Warm cafe theme',
            'background' => 'cream paper with subtle grain',
            'print_target' => 'black-and-white printer safe',
        ],
        'content' => [
            'title' => [
                'text' => '와이파이 연결',
                'x_pc' => 50,
                'y_pc' => 10,
                'style' => 'Bold title style',
            ],
            'wifi_icon' => [
                'x_pc' => 50,
                'y_pc' => 20,
                'width_pc' => 15,
                'style' => 'Simple line icon',
            ],
            'message' => [
                'text' => '스캔하여 연결하세요.',
                'x_pc' => 50,
                'y_pc' => 62,
            ],
            'footer' => [
                'text' => '제작: WIFI NOTE',
                'x_pc' => 50,
                'y_pc' => 96,
            ],
        ],
        'placeholders' => [
            'ssid' => [
                'box_x_pc' => 50,
                'box_y_pc' => 40,
                'box_width_pc' => 70,
                'box_height_pc' => 8,
                'label' => 'SSID:',
                'label_position' => 'outside_above',
                'box_fill' => '#FFFFFF',
                'box_fill_note' => 'solid flat white cutout, no background pattern bleeding through',
            ],
            'password' => [
                'box_x_pc' => 50,
                'box_y_pc' => 52,
                'box_width_pc' => 70,
                'box_height_pc' => 8,
                'label' => 'PASSWORD:',
                'label_position' => 'outside_above',
                'box_fill' => '#FFFFFF',
                'box_fill_note' => 'solid flat white cutout, no background pattern bleeding through',
            ],
            'qr' => [
                'x_pc' => 50,
                'y_pc' => 80,
                'width_pc' => 28,
                'style' => 'QR frame with clean edges',
            ],
        ],
    ]);

    expect($prompt)
        ->toContain('[Task] Generate a high-contrast futuristic Wi-Fi signage image ONLY.')
        ->toContain('[Canvas]')
        ->toContain('- Portrait canvas, aspect ratio 3:4.')
        ->toContain('[Style]')
        ->toContain('Warm cafe theme. cream paper with subtle grain. black-and-white printer safe.')
        ->toContain('1. Title "와이파이 연결": centered at x=50%, y=10%. Bold title style.')
        ->toContain('2. Wi-Fi icon: centered at x=50%, y=20%, width≈15% of canvas width. Simple line icon.')
        ->toContain('3. ssid placeholder box: centered at x=50%, y=40%, box width≈70%, height≈8% of canvas.')
        ->toContain('solid flat white cutout, no background pattern bleeding through')
        ->toContain('just outside/above the box')
        ->toContain('4. password placeholder box: centered at x=50%, y=52%, box width≈70%, height≈8% of canvas.')
        ->toContain('5. Message "스캔하여 연결하세요.": centered at x=50%, y=62%.')
        ->toContain('6. QR placeholder: square area centered at x=50%, y=80%, width≈28% of canvas. QR frame with clean edges.')
        ->toContain('7. Footer "제작: WIFI NOTE": centered at x=50%, y=96%.')
        ->toContain('[Strict rules]')
        ->toContain('Output the image only.');
});
