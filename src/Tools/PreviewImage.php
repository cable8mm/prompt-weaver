<?php

namespace Cable8mm\PromptWeaver\Tools;

use GdImage;
use RuntimeException;

final class PreviewImage
{
    private const CONFIG_FILENAME = 'config.json';

    private const CALIBRATED_CONFIG_FILENAME = 'calibrate.config.json';

    private string $fixtureDirectory;

    private string $configPath;

    private string $calibratedConfigPath;

    private string $backgroundPath;

    public function __construct(string $fixtureDirectory)
    {
        $this->fixtureDirectory = rtrim($fixtureDirectory, '/');
        $this->configPath = $this->fixtureDirectory.'/'.self::CONFIG_FILENAME;
        $this->calibratedConfigPath = $this->fixtureDirectory.'/'.self::CALIBRATED_CONFIG_FILENAME;
        $this->backgroundPath = $this->fixtureDirectory.'/image.png';
    }

    /**
     * Detect the actual white placeholder boxes in image.png and write the
     * corresponding coordinates to calibrate.config.json.
     *
     * @return array<string, float> Updated coordinates keyed by placeholder.
     */
    public function calibrate(): array
    {
        if (! is_file($this->configPath)) {
            throw new RuntimeException("Config file not found: {$this->configPath}");
        }

        if (! is_file($this->backgroundPath)) {
            throw new RuntimeException("Background image not found: {$this->backgroundPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($this->configPath), true, 512, JSON_THROW_ON_ERROR);
        $image = imagecreatefromstring((string) file_get_contents($this->backgroundPath));

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Unable to load background image: {$this->backgroundPath}");
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

        if (file_put_contents($this->calibratedConfigPath, $json) === false) {
            throw new RuntimeException("Unable to write calibrated config: {$this->calibratedConfigPath}");
        }

        return $updated;
    }

    /**
     * @param  array<string, string>  $options
     */
    public function render(string $outputPath, array $options = []): string
    {
        $configPath = is_file($this->calibratedConfigPath)
            ? $this->calibratedConfigPath
            : $this->configPath;

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

        return (new RenderPng)->render($config, $this->backgroundPath, $outputPath, $options);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function renderHtml(string $outputPath, array $options = []): string
    {
        $configPath = is_file($this->calibratedConfigPath)
            ? $this->calibratedConfigPath
            : $this->configPath;

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

        return (new RenderHtml)->render($config, $this->backgroundPath, $configPath, $outputPath, $options);
    }
}
