<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

use GdImage;

trait PlaceholderGeometryTrait
{
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
     * @param  array<string, mixed>  $placeholder
     * @return array{left:int, top:int, width:int, height:int, center_x:int, center_y:int}
     */
    private function qrPlaceholderBoxFromDimensions(array $placeholder, int $imageWidth, int $imageHeight): array
    {
        $widthPc = $placeholder['box_width_pc'] ?? $placeholder['width_pc'] ?? 0;
        $heightPc = $placeholder['box_height_pc'] ?? $placeholder['height_pc'] ?? null;
        if ($heightPc === null && is_numeric($widthPc)) {
            $heightPc = ((float) $widthPc * $imageWidth) / $imageHeight;
        }

        $centerX = (int) round(((float) ($placeholder['box_x_pc'] ?? $placeholder['x_pc'] ?? 0) / 100) * $imageWidth);
        $centerY = (int) round(((float) ($placeholder['box_y_pc'] ?? $placeholder['y_pc'] ?? 0) / 100) * $imageHeight);
        $width = (int) round(((float) $widthPc / 100) * $imageWidth);
        $height = (int) round(((float) ($heightPc ?? 0) / 100) * $imageHeight);

        return ['left' => $centerX - (int) round($width / 2), 'top' => $centerY - (int) round($height / 2), 'width' => $width, 'height' => $height, 'center_x' => $centerX, 'center_y' => $centerY];
    }

    private function percentageToPixels(float $percentage, int $size): float
    {
        return ($percentage / 100) * $size;
    }

    private function isWhitePixel(GdImage $image, int $x, int $y): bool
    {
        $color = imagecolorat($image, $x, $y);

        return (($color >> 16) & 255) >= 248
            && (($color >> 8) & 255) >= 248
            && ($color & 255) >= 248;
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
}
