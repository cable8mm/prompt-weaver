<?php

use Cable8mm\PromptWeaver\Tools\RenderHtml;

beforeEach(function () {
    $fixtureDir = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
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
    test()->configPath = $configPath;
    test()->outputPath = sys_get_temp_dir().'/prompt-weaver-render-html-'.bin2hex(random_bytes(4)).'.html';
});

afterEach(function () {
    if (isset($this->outputPath) && is_file($this->outputPath)) {
        unlink($this->outputPath);
    }
});

it('renders an html file with the expected structure', function () {
    $renderer = new RenderHtml;

    $result = $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath);

    expect($result)->toBe($this->outputPath);
    expect(is_file($this->outputPath))->toBeTrue();

    $html = file_get_contents($this->outputPath);
    expect($html)->toContain('<!doctype html>');
    expect($html)->toContain('<title>Prompt Weaver Preview</title>');
    expect($html)->toContain('class="background"');
    expect($html)->toContain('class="text-placeholder"');
    expect($html)->toContain('class="qr"');
    expect($html)->toContain('data:image/png;base64,');
});

it('renders html with ssid and password placeholder divs', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath);

    $html = file_get_contents($this->outputPath);
    expect($html)->toContain('id="ssid"');
    expect($html)->toContain('id="password"');
});

it('renders html with embedded qr code as data uri', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath);

    $html = file_get_contents($this->outputPath);
    expect($html)->toContain('data:image/png;base64,');
});

it('renders html with correct aspect ratio in css', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath);

    [$width, $height] = getimagesize($this->backgroundPath);

    $html = file_get_contents($this->outputPath);
    expect($html)->toContain('aspect-ratio:'.$width.'/'.$height.';');
});

it('renders html with custom ssid and password options', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath, [
        'ssid' => 'CUSTOM-SSID',
        'password' => 'CUSTOM-PASS',
    ]);

    expect(is_file($this->outputPath))->toBeTrue();
    $html = file_get_contents($this->outputPath);
    expect($html)->toContain('CUSTOM-SSID');
    expect($html)->toContain('CUSTOM-PASS');
});

it('renders html with custom qr payload option', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, $this->backgroundPath, $this->configPath, $this->outputPath, [
        'qr-payload' => 'WIFI:T:WPA;S:TEST;P:TEST;;',
    ]);

    expect(is_file($this->outputPath))->toBeTrue();
});

it('throws an exception when background image is missing', function () {
    $renderer = new RenderHtml;

    $renderer->render($this->config, '/nonexistent/image.png', $this->configPath, $this->outputPath);
})->throws(RuntimeException::class);
