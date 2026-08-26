<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Contracts\PromptInterface;

class ImagePrompt implements PromptInterface
{
    private ?string $promptString = null;

    /**
     * @param  array  $config  wifi-note template config JSON (canvas, style, content, placeholders)
     */
    public function __construct(
        private array $config,
    ) {}

    public function build(): void
    {
        $canvas = $this->config['canvas'];
        $style = $this->config['style'];
        $content = $this->config['content'];
        $placeholders = $this->config['placeholders'];
        $canvasDescription = $canvas['aspect_ratio'] === '1:1'
            ? '- Square canvas, aspect ratio 1:1. The artwork must fill the entire square canvas edge-to-edge. Do not place the design on an inner sheet, portrait page, A4 paper, card, or secondary background; do not add outer margins or a nested paper shape.'
            : "- Portrait canvas, aspect ratio {$canvas['aspect_ratio']}. The artwork must fill the entire canvas; do not place it on an inner sheet or secondary background.";

        $layoutLines = [];

        $step = 1;

        foreach ($content as $name => $element) {
            // Message and footer are emitted below with their dedicated
            // placement rules; do not add them again in this generic pass.
            if (in_array($name, ['message', 'footer'], true)) {
                continue;
            }

            if (! is_array($element) || ! isset($element['x_pc'], $element['y_pc'])) {
                continue;
            }

            $label = match ($name) {
                'wifi_icon' => 'Wi-Fi icon',
                default => ucfirst(str_replace('_', ' ', $name)),
            };
            $text = isset($element['text']) ? ' "'.$element['text'].'"' : '';
            $elementStyle = isset($element['style']) ? ' '.$element['style'].'.' : '';
            $width = isset($element['width_pc'])
                ? ", width={$element['width_pc']}% of canvas width exactly"
                : '';

            $layoutLines[] = "{$step}. {$label}{$text}: centered at x={$element['x_pc']}%, y={$element['y_pc']}%{$width}.{$elementStyle}";
            $step++;
        }

        // placeholder boxes like SSID / PASSWORD
        foreach (['ssid', 'password'] as $key) {
            if (! isset($placeholders[$key])) {
                continue;
            }
            $box = $placeholders[$key];
            $left = (float) $box['box_x_pc'] - ((float) $box['box_width_pc'] / 2);
            $right = (float) $box['box_x_pc'] + ((float) $box['box_width_pc'] / 2);
            $layoutLines[] = "{$step}. {$key} placeholder box: centered exactly at x={$box['box_x_pc']}%, y={$box['box_y_pc']}%, width={$box['box_width_pc']}% and height={$box['box_height_pc']}% of the full canvas exactly, from x={$left}% to x={$right}%.";
            $layoutLines[] = "   - The box's INTERIOR FILL must be solid {$box['box_fill']} — {$box['box_fill_note']}.";
            $layoutLines[] = "   - A small label \"{$box['label']}\" sits ".$this->describeLabelPosition($box['label_position']).' (against the surrounding background, not inside the white area).';
            $layoutLines[] = "   - Nothing else is drawn inside the box — it stays empty and pure {$box['box_fill']}.";
            $step++;
        }

        // Message
        $message = $content['message'];
        $layoutLines[] = "{$step}. Message \"{$message['text']}\": centered at x={$message['x_pc']}%, y={$message['y_pc']}%.";
        $step++;

        // QR
        $qr = $placeholders['qr'];
        $layoutLines[] = "{$step}. QR placeholder: square area centered exactly at x={$qr['x_pc']}%, y={$qr['y_pc']}%, width={$qr['width_pc']}% of the full canvas exactly. {$qr['style']}. The square must have a clearly visible continuous outer border in a color that strongly contrasts with both the solid white interior and the surrounding background, with no QR code drawn inside; keep the border geometrically square so calibration can detect it.";
        $step++;

        // Footer
        $footer = $content['footer'];
        $layoutLines[] = "{$step}. Footer \"{$footer['text']}\": centered at x={$footer['x_pc']}%, y={$footer['y_pc']}%.";

        $template = file_get_contents(__DIR__.'/../stubs/image.prompt');

        $this->promptString = strtr($template, [
            '{{ aspect_ratio }}' => $canvas['aspect_ratio'],
            '{{ canvas_description }}' => $canvasDescription,
            '{{ style }}' => $this->joinSentences([
                $style['theme'],
                $style['background'],
                $style['print_target'],
            ]),
            '{{ layout_items }}' => implode("\n", $layoutLines),
        ]);
    }

    public function prompt(): ?string
    {
        return $this->promptString;
    }

    private function describeLabelPosition(string $position): string
    {
        return match ($position) {
            'outside_above' => 'just outside/above the box',
            'outside_below' => 'just outside/below the box',
            'inside_top' => 'inside the box, aligned to the top edge',
            default => $position,
        };
    }

    /**
     * Join sentence fragments with a single trailing period each.
     *
     * @param  array<int, string>  $parts
     */
    private function joinSentences(array $parts): string
    {
        return implode(' ', array_map(
            fn (string $part): string => rtrim(trim($part), '.').'.',
            $parts,
        ));
    }
}
