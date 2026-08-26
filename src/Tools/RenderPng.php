<?php

namespace Cable8mm\PromptWeaver\Tools;

use GdImage;
use RuntimeException;

final class RenderPng
{
    use Traits\ConfigHelperTrait;
    use Traits\PlaceholderGeometryTrait;
    use Traits\QrHelperTrait;
    use Traits\TypographyTrait;
    use Traits\WifiHelperTrait;

    private const DEFAULT_SSID = 'WIFI-NOTE';

    private const DEFAULT_PASSWORD = 'WIFI-PASSWORD';

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $options
     */
    public function render(array $config, string $backgroundPath, string $outputPath, array $options = []): string
    {
        if (! is_file($backgroundPath)) {
            throw new RuntimeException("Background image not found: {$backgroundPath}");
        }

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
            0,
            $this->canvasWidthMm($config),
        );

        $this->drawPlaceholderText(
            $baseImage,
            $this->configPlaceholder($config, 'password'),
            $password,
            0,
            $this->canvasWidthMm($config),
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

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $placeholder
     */
    private function drawPlaceholderText(GdImage $image, array $placeholder, string $text, int $verticalAdjustment = 0, float $canvasWidthMm = 0): void
    {
        $box = $this->placeholderBox($image, $placeholder);
        $font = $this->fontPath();
        $fontSize = $this->typographyPixels($placeholder, imagesx($image), $canvasWidthMm);
        $colorHex = (string) ($placeholder['color'] ?? '#111111');
        $color = $this->allocateColor($image, $colorHex);

        $this->drawTextCenteredInBox($image, $font, $fontSize, $text, $box, $color, $verticalAdjustment);
    }

    /**
     * @param  array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}  $box
     */
    private function drawTextCenteredInBox(GdImage $image, string $font, int $fontSize, string $text, array $box, int $color, int $verticalAdjustment = 0): void
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
        $y += max(0, (int) round($fontSize * 0.05) - $verticalAdjustment);

        imagettftext($image, $fontSize, 0, $x, $y, $color, $font, $text);
    }

    /**
     * @param  array<string, mixed>  $placeholder
     */
    private function drawQr(GdImage $image, array $placeholder, string $payload): void
    {
        $box = $this->qrPlaceholderBox($image, $placeholder);
        $boxSize = min($box['width'], $box['height']);
        $padding = max(10, (int) round($boxSize * 0.1));
        $qrSize = max(64, $boxSize - ($padding * 2));
        $qrBinary = $this->qrBinary($placeholder, $payload, imagesx($image), imagesy($image));
        $qrImage = imagecreatefromstring($qrBinary);

        if (! $qrImage instanceof GdImage) {
            throw new RuntimeException('Unable to render QR image.');
        }

        $qrLeft = (int) round($box['center_x'] - ($boxSize / 2) + $padding);
        $qrTop = (int) round($box['center_y'] - ($boxSize / 2) + $padding);

        imagecopy($image, $qrImage, $qrLeft, $qrTop, 0, 0, $qrSize, $qrSize);
    }

    private function allocateColor(GdImage $image, string $hex): int
    {
        if (! preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $matches)) {
            throw new RuntimeException("Invalid color: {$hex}");
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
        $fontPath = dirname(__DIR__, 2).'/fonts/AtkinsonHyperlegible-Regular.ttf';

        if (is_file($fontPath)) {
            return $fontPath;
        }

        throw new RuntimeException("Preview font file not found: {$fontPath}");
    }

    /** @param array<string, mixed> $config */
    private function canvasWidthMm(array $config): float
    {
        $widthMm = $config['canvas']['width_mm'] ?? 0;

        return is_numeric($widthMm) ? (float) $widthMm : 0.0;
    }
}
