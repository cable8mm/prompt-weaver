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
    private const CONFIG_FILENAME = 'config.json';

    private const CALIBRATED_CONFIG_FILENAME = 'calibrate.config.json';

    private const DEFAULT_SSID = 'WIFI-NOTE';

    private const DEFAULT_PASSWORD = 'WIFI-PASSWORD';

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

        $updated = [];

        foreach (['ssid', 'password'] as $key) {
            $placeholder = $config['placeholders'][$key] ?? null;

            if (! is_array($placeholder)) {
                continue;
            }

            $box = $this->placeholderBox($image, $placeholder);
            $centerY = $this->findWhiteAreaCenterY($image, $box);

            if ($centerY === null) {
                continue;
            }

            $coordinate = round(($centerY / imagesy($image)) * 100, 2);
            $config['placeholders'][$key]['box_y_pc'] = $coordinate;
            $updated[$key] = $coordinate;
        }

        $qr = $config['placeholders']['qr'] ?? null;

        if (is_array($qr)) {
            $qrBox = $this->findQrBox($image, $qr);

            if ($qrBox !== null) {
                $config['placeholders']['qr']['x_pc'] = round(($qrBox['center_x'] / imagesx($image)) * 100, 2);
                $config['placeholders']['qr']['y_pc'] = round(($qrBox['center_y'] / imagesy($image)) * 100, 2);
                $config['placeholders']['qr']['width_pc'] = round(($qrBox['width'] / imagesx($image)) * 100, 2);
                $updated['qr_x'] = $config['placeholders']['qr']['x_pc'];
                $updated['qr_y'] = $config['placeholders']['qr']['y_pc'];
                $updated['qr_width'] = $config['placeholders']['qr']['width_pc'];
            }
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
        $fixtureDirectory = rtrim($fixtureDirectory, '/');
        $configPath = $fixtureDirectory.'/'.self::CALIBRATED_CONFIG_FILENAME;
        $backgroundPath = $fixtureDirectory.'/image.png';

        if (! is_file($configPath)) {
            $configPath = $fixtureDirectory.'/'.self::CONFIG_FILENAME;
        }

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
            1,
        );

        $this->drawPlaceholderText(
            $baseImage,
            $this->configPlaceholder($config, 'password'),
            $password,
            2,
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
    private function drawPlaceholderText(GdImage $image, array $placeholder, string $text, int $verticalAdjustment = 0): void
    {
        $box = $this->placeholderBox($image, $placeholder);
        $font = $this->fontPath();
        $fontSize = (int) ($placeholder['font_size_px'] ?? 36);
        $colorHex = (string) ($placeholder['color'] ?? '#111111');
        $color = $this->allocateColor($image, $colorHex);

        $this->drawTextCenteredInBox($image, $font, $fontSize, $text, $box, $color, $verticalAdjustment);
    }

    /**
     * @param  array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}  $box
     */
    private function findWhiteAreaCenterY(GdImage $image, array $box): ?int
    {
        $left = max(0, $box['left'] + 4);
        $right = min(imagesx($image) - 1, $box['left'] + $box['width'] - 5);

        if ($right <= $left) {
            return null;
        }

        $searchRadius = max($box['height'] * 2, (int) round(imagesy($image) * 0.08));
        $startY = max(0, $box['center_y'] - $searchRadius);
        $endY = min(imagesy($image) - 1, $box['center_y'] + $searchRadius);
        $runs = [];
        $runStart = null;

        for ($y = $startY; $y <= $endY; $y++) {
            $whiteRatio = $this->whitePixelRatio($image, $left, $right, $y);
            $isWhiteRow = $whiteRatio >= 0.8;

            if ($isWhiteRow && $runStart === null) {
                $runStart = $y;
            }

            if ((! $isWhiteRow || $y === $endY) && $runStart !== null) {
                $runEnd = $isWhiteRow && $y === $endY ? $y : $y - 1;

                if ($runEnd - $runStart + 1 >= max(12, (int) round($box['height'] * 0.5))) {
                    $runs[] = [$runStart, $runEnd];
                }

                $runStart = null;
            }
        }

        if ($runs === []) {
            return null;
        }

        usort($runs, fn (array $first, array $second): int => abs((($first[0] + $first[1]) / 2) - $box['center_y']) <=>
            abs((($second[0] + $second[1]) / 2) - $box['center_y'])
        );

        return (int) round(($runs[0][0] + $runs[0][1]) / 2);
    }

    private function whitePixelRatio(GdImage $image, int $left, int $right, int $y): float
    {
        $whitePixels = 0;
        $pixelCount = $right - $left + 1;

        for ($x = $left; $x <= $right; $x++) {
            $color = imagecolorat($image, $x, $y);
            $red = ($color >> 16) & 255;
            $green = ($color >> 8) & 255;
            $blue = $color & 255;

            if ($red >= 248 && $green >= 248 && $blue >= 248) {
                $whitePixels++;
            }
        }

        return $pixelCount > 0 ? $whitePixels / $pixelCount : 0.0;
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

        $writer = new Writer(new GDLibRenderer($qrSize, 0));
        $qrBinary = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::H());

        $qrImage = imagecreatefromstring($qrBinary);

        if (! $qrImage instanceof GdImage) {
            throw new RuntimeException('Unable to render QR image.');
        }

        $qrLeft = (int) round($box['center_x'] - ($qrSize / 2));
        $qrTop = (int) round($box['center_y'] - ($qrSize / 2));

        imagecopy($image, $qrImage, $qrLeft, $qrTop, 0, 0, $qrSize, $qrSize);
    }

    /**
     * @param  array<string, mixed>  $placeholder
     * @return array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}
     */
    private function qrPlaceholderBox(GdImage $image, array $placeholder): array
    {
        $widthPc = $placeholder['box_width_pc'] ?? $placeholder['width_pc'] ?? 0;
        $heightPc = $placeholder['box_height_pc'] ?? $placeholder['height_pc'] ?? null;

        // QR placeholders are square. Convert the width percentage to the
        // equivalent height percentage when height_pc is omitted.
        if ($heightPc === null && is_numeric($widthPc)) {
            $heightPc = ((float) $widthPc * imagesx($image)) / imagesy($image);
        }

        return $this->placeholderBox($image, [
            ...$placeholder,
            'box_x_pc' => $placeholder['box_x_pc'] ?? $placeholder['x_pc'] ?? 0,
            'box_y_pc' => $placeholder['box_y_pc'] ?? $placeholder['y_pc'] ?? 0,
            'box_width_pc' => $widthPc,
            'box_height_pc' => $heightPc ?? 0,
        ]);
    }

    /**
     * Find the large white QR frame in the configured vertical neighborhood.
     *
     * @param  array<string, mixed>  $placeholder
     * @return array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}|null
     */
    private function findQrBox(GdImage $image, array $placeholder): ?array
    {
        $expected = $this->qrPlaceholderBox($image, $placeholder);
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);
        // Keep the search in the QR section so the larger credential boxes
        // above it cannot be mistaken for the QR frame.
        $searchRadius = (int) round($imageHeight * 0.2);
        $startY = max(0, $expected['center_y'] - $searchRadius);
        $endY = min($imageHeight - 1, $expected['center_y'] + $searchRadius);
        $minimumWidth = max(40, (int) round($expected['width'] * 0.5));
        $best = null;
        $bestScore = PHP_FLOAT_MAX;

        for ($y = $startY; $y <= $endY; $y++) {
            $runStart = null;

            for ($x = 0; $x <= $imageWidth; $x++) {
                $isWhite = $x < $imageWidth && $this->isWhitePixel($image, $x, $y);

                if ($isWhite && $runStart === null) {
                    $runStart = $x;
                }

                if ((! $isWhite || $x === $imageWidth) && $runStart !== null) {
                    $runEnd = $x - 1;
                    $width = $runEnd - $runStart + 1;

                    if ($width >= $minimumWidth) {
                        // A single long white scanline is not enough to identify
                        // the QR frame: the generated artwork also contains wide
                        // white/ivory background areas. Measure how far this same
                        // run continues vertically and prefer square candidates.
                        $top = $y;
                        $bottom = $y;

                        for ($candidateY = $y - 1; $candidateY >= $startY; $candidateY--) {
                            if ($this->whitePixelRatio($image, $runStart, $runEnd, $candidateY) < 0.8) {
                                break;
                            }

                            $top = $candidateY;
                        }

                        for ($candidateY = $y + 1; $candidateY <= $endY; $candidateY++) {
                            if ($this->whitePixelRatio($image, $runStart, $runEnd, $candidateY) < 0.8) {
                                break;
                            }

                            $bottom = $candidateY;
                        }

                        $height = $bottom - $top + 1;
                        $aspectRatio = $width / max(1, $height);

                        // The QR placeholder is square. Reject broad horizontal
                        // background runs before scoring the remaining candidates.
                        if ($height < $minimumWidth * 0.5 || $aspectRatio < 0.6 || $aspectRatio > 1.6) {
                            $runStart = null;

                            continue;
                        }

                        $centerX = ($runStart + $runEnd) / 2;
                        $centerY = ($top + $bottom) / 2;
                        $score = abs(log($aspectRatio))
                            + (abs($centerY - $expected['center_y']) / $imageHeight) * 2
                            + (abs($width - $expected['width']) / $imageWidth) * 0.25;

                        if ($score < $bestScore) {
                            $bestScore = $score;
                            $best = [
                                'left' => $runStart,
                                'right' => $runEnd,
                                'width' => $width,
                                'top' => $top,
                                'bottom' => $bottom,
                                'center_x' => (int) round($centerX),
                                'center_y' => (int) round($centerY),
                            ];
                        }
                    }

                    $runStart = null;
                }
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'left' => $best['left'],
            'top' => $best['top'],
            'width' => $best['width'],
            'height' => $best['bottom'] - $best['top'] + 1,
            'center_x' => $best['center_x'],
            'center_y' => $best['center_y'],
        ];
    }

    private function isWhitePixel(GdImage $image, int $x, int $y): bool
    {
        $color = imagecolorat($image, $x, $y);

        return (($color >> 16) & 255) >= 248
            && (($color >> 8) & 255) >= 248
            && ($color & 255) >= 248;
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
