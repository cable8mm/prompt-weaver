<?php

use Cable8mm\PromptWeaver\Tools\RenderPng;

beforeEach(function () {
    $fixtureDir = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $configPath = $fixtureDir.'/config.json';
    $imagePath = $fixtureDir.'/image.png';

    if (! is_file($configPath) || ! is_file($imagePath)) {
        throw new RuntimeException('Test fixture files not found.');
    }

    /** @var array<string, mixed> $config */
    test()->config = json_decode(
        (string) file_get_contents($configPath),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    test()->backgroundPath = $imagePath;
    test()->outputPath = sys_get_temp_dir().'/prompt-weaver-render-png-'.bin2hex(random_bytes(4)).'.png';
});

afterEach(function () {
    if (isset($this->outputPath) && is_file($this->outputPath)) {
        unlink($this->outputPath);
    }
});

it('renders a png with the same dimensions as the background image', function () {
    $renderer = new RenderPng;

    $result = $renderer->render($this->config, $this->backgroundPath, $this->outputPath);

    expect($result)->toBe($this->outputPath);
    expect(is_file($this->outputPath))->toBeTrue();

    [$baseWidth, $baseHeight] = getimagesize($this->backgroundPath);
    [$previewWidth, $previewHeight] = getimagesize($this->outputPath);

    expect([$previewWidth, $previewHeight])->toBe([$baseWidth, $baseHeight]);
});

it('renders a png that differs from the original background image', function () {
    $renderer = new RenderPng;

    $renderer->render($this->config, $this->backgroundPath, $this->outputPath);

    expect(md5_file($this->outputPath))->not->toBe(md5_file($this->backgroundPath));
});

it('renders a png with qr dark pixels in the configured area', function () {
    $renderer = new RenderPng;

    $renderer->render($this->config, $this->backgroundPath, $this->outputPath);

    $preview = imagecreatefrompng($this->outputPath);
    expect($preview)->toBeInstanceOf(GdImage::class);

    // QR is rendered in the lower-center area (around x_pc=50, y_pc=76)
    $darkPixels = 0;
    for ($y = 1000; $y < 1300; $y++) {
        for ($x = 390; $x < 700; $x++) {
            $color = imagecolorat($preview, $x, $y) & 0xFFFFFF;

            if ($color < 0x333333) {
                $darkPixels++;
            }
        }
    }

    expect($darkPixels)->toBeGreaterThan(1000);
    imagedestroy($preview);
});

it('centers the qr image inside its placeholder box', function () {
    $backgroundPath = sys_get_temp_dir().'/prompt-weaver-qr-background-'.bin2hex(random_bytes(4)).'.png';
    $background = imagecreatetruecolor(1000, 1000);
    $white = imagecolorallocate($background, 255, 255, 255);
    imagefill($background, 0, 0, $white);
    imagepng($background, $backgroundPath);
    imagedestroy($background);

    $config = [
        'placeholders' => [
            'ssid' => [
                'box_x_pc' => 50,
                'box_y_pc' => 20,
                'box_width_pc' => 40,
                'box_height_pc' => 10,
                'font_size_px' => 20,
            ],
            'password' => [
                'box_x_pc' => 50,
                'box_y_pc' => 35,
                'box_width_pc' => 40,
                'box_height_pc' => 10,
                'font_size_px' => 20,
            ],
            'qr' => [
                'x_pc' => 50,
                'y_pc' => 70,
                'width_pc' => 40,
            ],
        ],
    ];

    try {
        (new RenderPng)->render($config, $backgroundPath, $this->outputPath, [
            'qr-payload' => 'WIFI:T:WPA;S:TEST;P:TEST;;',
        ]);

        $preview = imagecreatefrompng($this->outputPath);
        expect($preview)->toBeInstanceOf(GdImage::class);

        $darkPixels = [];
        for ($y = 400; $y < 1000; $y++) {
            for ($x = 200; $x < 800; $x++) {
                if ((imagecolorat($preview, $x, $y) & 0xFFFFFF) < 0x333333) {
                    $darkPixels[] = [$x, $y];
                }
            }
        }

        $minX = min(array_column($darkPixels, 0));
        $maxX = max(array_column($darkPixels, 0));
        $minY = min(array_column($darkPixels, 1));
        $maxY = max(array_column($darkPixels, 1));

        expect(($minX + $maxX) / 2)->toBeBetween(490.0, 510.0);
        expect(($minY + $maxY) / 2)->toBeBetween(690.0, 710.0);
        expect($maxX - $minX)->toBeGreaterThan(250);
        expect($maxY - $minY)->toBeGreaterThan(250);

        imagedestroy($preview);
    } finally {
        if (is_file($backgroundPath)) {
            unlink($backgroundPath);
        }
    }
});

it('renders a png with custom ssid and password options', function () {
    $renderer = new RenderPng;

    $renderer->render($this->config, $this->backgroundPath, $this->outputPath, [
        'ssid' => 'TEST-WIFI',
        'password' => 'TEST-PASS',
    ]);

    expect(is_file($this->outputPath))->toBeTrue();

    [$baseWidth, $baseHeight] = getimagesize($this->backgroundPath);
    [$previewWidth, $previewHeight] = getimagesize($this->outputPath);

    expect([$previewWidth, $previewHeight])->toBe([$baseWidth, $baseHeight]);
});

it('throws an exception when background image is missing', function () {
    $renderer = new RenderPng;

    $renderer->render($this->config, '/nonexistent/image.png', $this->outputPath);
})->throws(RuntimeException::class);
