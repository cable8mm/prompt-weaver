<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Tools\Calibrator;
use Cable8mm\PromptWeaver\Tools\RenderHtml;
use Cable8mm\PromptWeaver\Tools\RenderPng;
use GdImage;
use RuntimeException;

final class PreviewImage
{
    private const CONFIG_FILENAME = 'config.json';

    private const CALIBRATED_CONFIG_FILENAME = 'calibrate.config.json';

    /**
     * Detect the actual white placeholder boxes in image.png and write the
     * corresponding coordinates to calibrate.config.json.
     *
     * @return array<string, float> Updated coordinates keyed by placeholder.
     */
    public function calibrate(string $fixtureDirectory): array
    {
        $fixtureDirectory = rtrim($fixtureDirectory, '/');
        $configPath = $fixtureDirectory.'/'.self::CONFIG_FILENAME;
        $calibratedConfigPath = $fixtureDirectory.'/'.self::CALIBRATED_CONFIG_FILENAME;
        $backgroundPath = $fixtureDirectory.'/image.png';

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }

        if (! is_file($backgroundPath)) {
            throw new RuntimeException("Background image not found: {$backgroundPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $image = imagecreatefromstring((string) file_get_contents($backgroundPath));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Unable to load background image: {$backgroundPath}");
        }

        $calibrator = new Calibrator;
        $config = $calibrator->calibrate($config, $image);

        $updated = [];

        foreach (['ssid', 'password'] as $key) {
            if (isset($config['placeholders'][$key]['box_y_pc'])) {
                $updated[$key] = $config['placeholders'][$key]['box_y_pc'];
            }
        }

        if (isset($config['placeholders']['qr'])) {
            $updated['qr_x'] = $config['placeholders']['qr']['x_pc'] ?? 0;
            $updated['qr_y'] = $config['placeholders']['qr']['y_pc'] ?? 0;
            $updated['qr_width'] = $config['placeholders']['qr']['width_pc'] ?? 0;
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($calibratedConfigPath, $json) === false) {
            throw new RuntimeException("Unable to write calibrated config: {$calibratedConfigPath}");
        }

        return $updated;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function render(string $fixtureDirectory, string $outputPath, array $options = []): string
    {
        return (new RenderPng)->render($fixtureDirectory, $outputPath, $options);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function renderHtml(string $fixtureDirectory, string $outputPath, array $options = []): string
    {
        return (new RenderHtml)->render($fixtureDirectory, $outputPath, $options);
    }
}
