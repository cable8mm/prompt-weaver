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

    private function typographyPixels(array $placeholder, int $dpi): int
    {
        $typography = $this->typography($placeholder);

        return (int) round($typography['unit'] === 'pt'
            ? $typography['value'] * $dpi / 72
            : $typography['value']);
    }
}
