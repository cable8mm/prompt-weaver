<?php

namespace Cable8mm\PromptWeaver\Tools;

use GdImage;

final class Calibrator
{
    use Traits\PlaceholderGeometryTrait;

    public function __construct(private readonly ?string $imagePath = null) {}

    /**
     * Detect the actual white placeholder boxes and QR frame in the image,
     * then return the config array with updated coordinates.
     *
     * @param  array<string, mixed>  $config  Parsed raw.config.json
     * @return array<string, mixed> Updated config with calibrated coordinates
     */
    public function calibrate(array $config, GdImage $image): array
    {
        $updated = [];
        $previousBox = null;

        foreach (['ssid', 'password'] as $key) {
            $placeholder = $config['placeholders'][$key] ?? null;

            if (! is_array($placeholder)) {
                continue;
            }

            $box = $this->placeholderBox($image, $placeholder);
            $minimumCenterY = $previousBox === null
                ? null
                : $previousBox['center_y'] + (int) round($previousBox['height'] / 2);
            $centerY = $this->findWhiteAreaCenterY($image, $box, $minimumCenterY);

            if ($centerY === null) {
                continue;
            }

            $coordinate = round(($centerY / imagesy($image)) * 100, 2);
            $config['placeholders'][$key]['box_y_pc'] = $coordinate;
            $updated[$key] = $coordinate;
            $previousBox = $box;
            $previousBox['center_y'] = $centerY;
        }

        $qr = $config['placeholders']['qr'] ?? null;

        if (is_array($qr)) {
            if ($this->imagePath === null) {
                throw new \RuntimeException('An image path is required for Python QR calibration.');
            }

            $qrBox = (new PythonQrDetector)->detect($this->imagePath, $qr);

            if ($qrBox === null) {
                throw new \RuntimeException('Unable to detect the QR frame with the Python/OpenCV detector. Install uv and run calibration again.');
            }

            if ($qrBox !== null) {
                $config['placeholders']['qr']['x_pc'] = round(($qrBox['center_x'] / imagesx($image)) * 100, 2);
                $config['placeholders']['qr']['y_pc'] = round(($qrBox['center_y'] / imagesy($image)) * 100, 2);
                $config['placeholders']['qr']['width_pc'] = round(($qrBox['width'] / imagesx($image)) * 100, 2);
                $updated['qr_x'] = $config['placeholders']['qr']['x_pc'];
                $updated['qr_y'] = $config['placeholders']['qr']['y_pc'];
                $updated['qr_width'] = $config['placeholders']['qr']['width_pc'];
            }
        }

        return $config;
    }

    /**
     * @param  array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}  $box
     */
    private function findWhiteAreaCenterY(GdImage $image, array $box, ?int $minimumCenterY = null): ?int
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

                $runCenterY = ($runStart + $runEnd) / 2;

                if (
                    $runEnd - $runStart + 1 >= max(12, (int) round($box['height'] * 0.5))
                    && ($minimumCenterY === null || $runCenterY > $minimumCenterY)
                ) {
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
}
