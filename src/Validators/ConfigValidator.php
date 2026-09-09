<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Validators;

use Cable8mm\PromptWeaver\Enums\PrintTarget;
use RuntimeException;

final class ConfigValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(array $config, ?string $source = null): void
    {
        foreach (['canvas', 'style', 'content', 'placeholders'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                throw new RuntimeException($this->message(
                    "Config is missing object '{$key}'",
                    $source,
                ));
            }
        }

        $aspectRatio = $config['canvas']['aspect_ratio'] ?? null;
        if (
            ! is_string($aspectRatio)
            || ! preg_match('/^\d+(?:\.\d+)?:\d+(?:\.\d+)?$/', $aspectRatio)
        ) {
            throw new RuntimeException($this->message(
                'Config has an invalid canvas.aspect_ratio',
                $source,
            ));
        }

        if (array_key_exists('width_mm', $config['canvas'])
            && (! is_numeric($config['canvas']['width_mm']) || (float) $config['canvas']['width_mm'] <= 0)) {
            throw new RuntimeException($this->message(
                'Config canvas has an invalid width_mm',
                $source,
            ));
        }

        $printTarget = $config['style']['print_target'] ?? null;
        if (! is_string($printTarget) || PrintTarget::tryFrom($printTarget) === null) {
            throw new RuntimeException($this->message(
                'Config has an invalid style.print_target',
                $source,
            ));
        }

        foreach (['ssid', 'password'] as $placeholder) {
            $fontPt = $config['placeholders'][$placeholder]['font_size_pt'] ?? null;
            if (array_key_exists('font_size_px', $config['placeholders'][$placeholder])) {
                throw new RuntimeException($this->message(
                    "Config placeholder '{$placeholder}' uses removed font_size_px",
                    $source,
                ));
            }
            if ($fontPt === null) {
                throw new RuntimeException($this->message(
                    "Config placeholder '{$placeholder}' is missing font_size_pt",
                    $source,
                ));
            }
            if ($fontPt !== null && (! is_numeric($fontPt) || (float) $fontPt < 6 || (float) $fontPt > 96)) {
                throw new RuntimeException($this->message(
                    "Config placeholder '{$placeholder}' has an invalid font_size_pt",
                    $source,
                ));
            }
        }

        if (isset($config['placeholders']['ssid']['font_size_pt']) || isset($config['placeholders']['password']['font_size_pt'])) {
            foreach (['width_mm', 'height_mm', 'dpi'] as $key) {
                if (! isset($config['canvas'][$key]) || ! is_numeric($config['canvas'][$key]) || (float) $config['canvas'][$key] <= 0) {
                    throw new RuntimeException($this->message(
                        "Config canvas is missing valid {$key} metadata",
                        $source,
                    ));
                }
            }
        }
    }

    private function message(string $message, ?string $source): string
    {
        return $message.($source === null ? '.' : ": {$source}");
    }
}
