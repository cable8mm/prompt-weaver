<?php

use Cable8mm\PromptWeaver\Tools\Calibrator;

beforeEach(function () {
    $fixtureDir = dirname(__DIR__).'/Fixtures/openrouter/cafe-restaurant';
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

    $image = imagecreatefromstring((string) file_get_contents($imagePath));
    expect($image)->toBeInstanceOf(GdImage::class);
    test()->image = $image;
});

afterEach(function () {
    if (isset($this->image) && $this->image instanceof GdImage) {
        unset($this->image);
    }
});

it('updates ssid and password box_y_pc values after calibration', function () {
    $calibrator = new Calibrator;

    $result = $calibrator->calibrate($this->config, $this->image);

    expect($result)->toHaveKey('placeholders');
    expect($result['placeholders'])->toHaveKey('ssid');
    expect($result['placeholders'])->toHaveKey('password');

    $ssidBoxY = $result['placeholders']['ssid']['box_y_pc'] ?? null;
    $passwordBoxY = $result['placeholders']['password']['box_y_pc'] ?? null;

    expect($ssidBoxY)->toBeFloat()->toBeGreaterThan(0);
    expect($passwordBoxY)->toBeFloat()->toBeGreaterThan(0);

    expect($passwordBoxY)->toBeGreaterThan($ssidBoxY);
});

it('updates qr position and width after calibration', function () {
    $calibrator = new Calibrator;

    $result = $calibrator->calibrate($this->config, $this->image);

    expect($result)->toHaveKey('placeholders');
    expect($result['placeholders'])->toHaveKey('qr');

    $qrX = $result['placeholders']['qr']['x_pc'] ?? null;
    $qrY = $result['placeholders']['qr']['y_pc'] ?? null;
    $qrWidth = $result['placeholders']['qr']['width_pc'] ?? null;

    expect($qrX)->toBeFloat()->toBeGreaterThan(45.0);
    expect($qrY)->toBeFloat()->toBeGreaterThan(75.0);
    expect($qrWidth)->toBeFloat()->toBeGreaterThan(25.0);
});

it('does not modify the original config array', function () {
    $originalConfig = $this->config;
    $calibrator = new Calibrator;

    $result = $calibrator->calibrate($this->config, $this->image);

    // Original config should remain unchanged (new array returned)
    expect($result)->not->toBe($originalConfig);
    expect($originalConfig['placeholders']['ssid']['box_y_pc'])
        ->toBe($this->config['placeholders']['ssid']['box_y_pc']);
});

it('handles config without ssid/password/qr placeholders gracefully', function () {
    $minimalConfig = [
        'placeholders' => [],
    ];

    $calibrator = new Calibrator;
    $result = $calibrator->calibrate($minimalConfig, $this->image);

    expect($result)->toHaveKey('placeholders');
    expect($result['placeholders'])->toBeEmpty();
});

it('returns calibrated values within valid percentage range', function () {
    $calibrator = new Calibrator;

    $result = $calibrator->calibrate($this->config, $this->image);

    foreach (['ssid', 'password'] as $key) {
        $boxY = $result['placeholders'][$key]['box_y_pc'] ?? null;
        expect($boxY)->toBeFloat();
        expect($boxY)->toBeGreaterThanOrEqual(0.0);
        expect($boxY)->toBeLessThanOrEqual(100.0);
    }

    $qrX = $result['placeholders']['qr']['x_pc'] ?? null;
    $qrY = $result['placeholders']['qr']['y_pc'] ?? null;
    $qrWidth = $result['placeholders']['qr']['width_pc'] ?? null;

    expect($qrX)->toBeFloat()->toBeBetween(0, 100);
    expect($qrY)->toBeFloat()->toBeBetween(0, 100);
    expect($qrWidth)->toBeFloat()->toBeBetween(0, 100);
});
