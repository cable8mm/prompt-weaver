<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

trait TypographyTrait
{
    /**
     * Returns the configured typography without applying a renderer-specific scale.
     * New configs use points; font_size_px remains a legacy image-pixel fallback.
     *
     * @param  array<string, mixed>  $placeholder
     * @return array{value: float, unit: string}
     */
    private function typography(array $placeholder): array
    {
        if (isset($placeholder['font_size_pt']) && is_numeric($placeholder['font_size_pt'])) {
            return ['value' => (float) $placeholder['font_size_pt'], 'unit' => 'pt'];
        }

        if (isset($placeholder['font_size_px']) && is_numeric($placeholder['font_size_px'])) {
            return ['value' => (float) $placeholder['font_size_px'], 'unit' => 'px'];
        }

        throw new \InvalidArgumentException('Placeholder is missing font_size_pt or font_size_px.');
    }

    private function typographyPixels(array $placeholder, int $imageWidth, float $canvasWidthMm): int
    {
        $typography = $this->typography($placeholder);

        if ($typography['unit'] === 'px') {
            return (int) round($typography['value']);
        }

        if ($imageWidth <= 0 || $canvasWidthMm <= 0) {
            throw new \InvalidArgumentException(
                'Point-based typography requires a positive canvas.width_mm and image width.'
            );
        }

        return (int) round(
            $typography['value'] * $imageWidth * 25.4 / ($canvasWidthMm * 72)
        );
    }
}
