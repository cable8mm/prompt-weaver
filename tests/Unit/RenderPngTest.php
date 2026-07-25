<?php

use Cable8mm\PromptWeaver\Tools\RenderPng;

beforeEach(function () {
    $fixtureDir = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
    $configPath = $fixtureDir.'/config.json';
    $imagePath = $fixtureDir.'/image.png';

    if (! is_file($configPath) || ! is_file($imagePath)) {
        throw new RuntimeException('Test fixture files not found.');
    }

    test()->fixtureDir = $fixtureDir;
    test()->outputPath = sys_get_temp_dir().'/prompt-weaver-render-png-'.bin2hex(random_bytes(4)).'.png';
});

afterEach(function () {
    if (isset($this->outputPath) && is_file($this->outputPath)) {
        unlink($this->outputPath);
    }
});

it('renders a png with the same dimensions as the background image', function () {
    $renderer = new RenderPng;

    $result = $renderer->render($this->fixtureDir, $this->outputPath);

    expect($result)->toBe($this->outputPath);
    expect(is_file($this->outputPath))->toBeTrue();

    [$baseWidth, $baseHeight] = getimagesize($this->fixtureDir.'/image.png');
    [$previewWidth, $previewHeight] = getimagesize($this->outputPath);

    expect([$previewWidth, $previewHeight])->toBe([$baseWidth, $baseHeight]);
});

it('renders a png that differs from the original background image', function () {
    $renderer = new RenderPng;

    $renderer->render($this->fixtureDir, $this->outputPath);

    expect(md5_file($this->outputPath))->not->toBe(md5_file($this->fixtureDir.'/image.png'));
});

it('renders a png with qr dark pixels in the configured area', function () {
    $renderer = new RenderPng;

    $renderer->render($this->fixtureDir, $this->outputPath);

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

it('renders a png with custom ssid and password options', function () {
    $renderer = new RenderPng;

    $renderer->render($this->fixtureDir, $this->outputPath, [
        'ssid' => 'TEST-WIFI',
        'password' => 'TEST-PASS',
    ]);

    expect(is_file($this->outputPath))->toBeTrue();

    [$baseWidth, $baseHeight] = getimagesize($this->fixtureDir.'/image.png');
    [$previewWidth, $previewHeight] = getimagesize($this->outputPath);

    expect([$previewWidth, $previewHeight])->toBe([$baseWidth, $baseHeight]);
});

it('throws an exception when config file is missing', function () {
    $renderer = new RenderPng;

    $renderer->render('/nonexistent/directory', $this->outputPath);
})->throws(RuntimeException::class);

it('throws an exception when background image is missing', function () {
    $renderer = new RenderPng;

    $fixtureDir = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
    $tempDir = sys_get_temp_dir().'/prompt-weaver-render-png-noimg-'.bin2hex(random_bytes(4));

    if (! is_dir($tempDir) && ! mkdir($tempDir, 0777, true) && ! is_dir($tempDir)) {
        throw new RuntimeException("Unable to create directory: {$tempDir}");
    }

    // Copy only config.json, not image.png
    copy($fixtureDir.'/config.json', $tempDir.'/config.json');

    $renderer->render($tempDir, $this->outputPath);
})->throws(RuntimeException::class);
