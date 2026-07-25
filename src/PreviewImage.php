<?php

namespace Cable8mm\PromptWeaver;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use GdImage;
use InvalidArgumentException;
use RuntimeException;

final class PreviewImage
{
    private const DEFAULT_SSID = 'WIFI-NOTE';

    private const DEFAULT_PASSWORD = '12345678';

    /**
     * @param  array<string, string>  $options
     */
    public function render(string $fixtureDirectory, string $outputPath, array $options = []): string
    {
        $configPath = rtrim($fixtureDirectory, '/').'/config.json';
        $backgroundPath = rtrim($fixtureDirectory, '/').'/image.png';

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }

        if (! is_file($backgroundPath)) {
            throw new RuntimeException("Background image not found: {$backgroundPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

        $baseImage = imagecreatefromstring((string) file_get_contents($backgroundPath));

        if (! $baseImage instanceof GdImage) {
            throw new RuntimeException("Unable to load background image: {$backgroundPath}");
        }

        imagealphablending($baseImage, true);
        imagesavealpha($baseImage, true);

        $ssid = $this->optionValue($options, $config, 'ssid', self::DEFAULT_SSID);
        $password = $this->optionValue($options, $config, 'password', self::DEFAULT_PASSWORD);
        $qrPayload = $options['qr-payload'] ?? $this->configValue($config, ['placeholders', 'qr', 'payload']);
        if (! is_string($qrPayload) || $qrPayload === '') {
            $qrPayload = $this->buildWifiPayload($ssid, $password);
        }

        $this->drawPlaceholderText(
            $baseImage,
            $this->configPlaceholder($config, 'ssid'),
            $ssid,
        );

        $this->drawPlaceholderText(
            $baseImage,
            $this->configPlaceholder($config, 'password'),
            $password,
        );

        $this->drawQr(
            $baseImage,
            $this->configPlaceholder($config, 'qr'),
            $qrPayload,
        );

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        if (! imagepng($baseImage, $outputPath)) {
            throw new RuntimeException("Unable to write preview image: {$outputPath}");
        }

        imagedestroy($baseImage);

        return $outputPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function configPlaceholder(array $config, string $key): array
    {
        $placeholder = $config['placeholders'][$key] ?? null;

        if (! is_array($placeholder)) {
            throw new InvalidArgumentException("Missing placeholder: {$key}");
        }

        return $placeholder;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function optionValue(array $options, array $config, string $key, string $default): string
    {
        $value = $options[$key] ?? $this->configValue($config, ['placeholders', $key, 'value']);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $path
     */
    private function configValue(array $config, array $path): mixed
    {
        $value = $config;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $placeholder
     */
    private function drawPlaceholderText(GdImage $image, array $placeholder, string $text): void
    {
        $box = $this->placeholderBox($image, $placeholder);
        $font = $this->fontPath();
        $fontSize = (int) ($placeholder['font_size_px'] ?? 36);
        $colorHex = (string) ($placeholder['color'] ?? '#111111');
        $color = $this->allocateColor($image, $colorHex);

        $this->drawTextCenteredInBox($image, $font, $fontSize, $text, $box, $color);
    }

    /**
     * @param  array<string, mixed>  $placeholder
     * @return array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}
     */
    private function placeholderBox(GdImage $image, array $placeholder): array
    {
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $centerX = (int) round($this->percentageToPixels((float) ($placeholder['box_x_pc'] ?? 0), $imageWidth));
        $centerY = (int) round($this->percentageToPixels((float) ($placeholder['box_y_pc'] ?? 0), $imageHeight));
        $width = (int) round($this->percentageToPixels((float) ($placeholder['box_width_pc'] ?? 0), $imageWidth));
        $height = (int) round($this->percentageToPixels((float) ($placeholder['box_height_pc'] ?? 0), $imageHeight));

        return [
            'left' => (int) round($centerX - $width / 2),
            'top' => (int) round($centerY - $height / 2),
            'width' => $width,
            'height' => $height,
            'center_x' => $centerX,
            'center_y' => $centerY,
        ];
    }

    /**
     * @param  array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}  $box
     */
    private function drawTextCenteredInBox(GdImage $image, string $font, int $fontSize, string $text, array $box, int $color): void
    {
        $bbox = imagettfbbox($fontSize, 0, $font, $text);

        if ($bbox === false) {
            throw new RuntimeException('Unable to measure text.');
        }

        $left = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
        $right = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
        $top = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
        $bottom = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);

        $textWidth = $right - $left;
        $textHeight = $bottom - $top;

        $x = (int) round($box['left'] + (($box['width'] - $textWidth) / 2) - $left);
        $y = (int) round($box['top'] + (($box['height'] - $textHeight) / 2) - $top);
        $y += max(4, (int) round($fontSize * 0.1));

        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @param  array<string, mixed>  $placeholder
     */
    private function drawQr(GdImage $image, array $placeholder, string $payload): void
    {
        $box = $this->placeholderBox($image, $placeholder);
        $boxSize = min($box['width'], $box['height']);
        $padding = max(10, (int) round($boxSize * 0.1));
        $qrSize = max(64, $boxSize - ($padding * 2));

        $writer = new Writer(new GDLibRenderer($qrSize, 0));
        $qrBinary = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::H());

        $qrImage = imagecreatefromstring($qrBinary);

        if (! $qrImage instanceof GdImage) {
            throw new RuntimeException('Unable to render QR image.');
        }

        $qrLeft = (int) round($box['center_x'] - ($qrSize / 2));
        $qrTop = (int) round($box['center_y'] - ($qrSize / 2));

        imagecopy($image, $qrImage, $qrLeft, $qrTop, 0, 0, $qrSize, $qrSize);
        imagedestroy($qrImage);
    }

    private function allocateColor(GdImage $image, string $hex): int
    {
        if (! preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $matches)) {
            throw new InvalidArgumentException("Invalid color: {$hex}");
        }

        $value = $matches[1];
        $red = hexdec(substr($value, 0, 2));
        $green = hexdec(substr($value, 2, 2));
        $blue = hexdec(substr($value, 4, 2));

        $color = imagecolorallocate($image, $red, $green, $blue);
        if ($color === false) {
            throw new RuntimeException("Unable to allocate color: {$hex}");
        }

        return $color;
    }

    private function fontPath(): string
    {
        $fontPath = dirname(__DIR__).'/fonts/AtkinsonHyperlegible-Regular.ttf';

        if (is_file($fontPath)) {
            return $fontPath;
        }

        throw new RuntimeException("Preview font file not found: {$fontPath}");
    }

    private function buildWifiPayload(string $ssid, string $password): string
    {
        return sprintf(
            'WIFI:T:WPA;S:%s;P:%s;;',
            $this->escapeWifiValue($ssid),
            $this->escapeWifiValue($password),
        );
    }

    private function escapeWifiValue(string $value): string
    {
        return str_replace(['\\', ';', ',', ':'], ['\\\\', '\\;', '\\,', '\\:'], $value);
    }

    private function percentageToPixels(float $percentage, int $size): float
    {
        return ($percentage / 100) * $size;
    }
}
