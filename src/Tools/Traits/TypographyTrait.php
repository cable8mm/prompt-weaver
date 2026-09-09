<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

trait TypographyTrait
{
    /**
     * Returns the configured typography without applying a renderer-specific scale.
     * Placeholder typography is always expressed in physical points.
     *
     * @param  array<string, mixed>  $placeholder
     * @return array{value: float, unit: string}
     */
    private function typography(array $placeholder): array
    {
        if (isset($placeholder['font_size_pt']) && is_numeric($placeholder['font_size_pt'])) {
            return ['value' => (float) $placeholder['font_size_pt'], 'unit' => 'pt'];
        }

        throw new \InvalidArgumentException('Placeholder is missing font_size_pt.');
    }

    private function typographyPixels(array $placeholder, int $imageWidth, float $canvasWidthMm): int
    {
        $typography = $this->typography($placeholder);

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
