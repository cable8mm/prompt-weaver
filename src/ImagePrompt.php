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

        $layoutLines = [];

        $step = 1;

        foreach ($content as $name => $element) {
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
                ? ", width≈{$element['width_pc']}% of canvas width"
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
            $layoutLines[] = "{$step}. {$key} placeholder box: centered at x={$box['box_x_pc']}%, y={$box['box_y_pc']}%, box width≈{$box['box_width_pc']}%, height≈{$box['box_height_pc']}% of canvas.";
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
        $layoutLines[] = "{$step}. QR placeholder: square area centered at x={$qr['x_pc']}%, y={$qr['y_pc']}%, width≈{$qr['width_pc']}% of canvas. {$qr['style']}.";
        $step++;

        // Footer
        $footer = $content['footer'];
        $layoutLines[] = "{$step}. Footer \"{$footer['text']}\": centered at x={$footer['x_pc']}%, y={$footer['y_pc']}%.";

        $template = file_get_contents(__DIR__.'/../stubs/image.prompt');

        $this->promptString = strtr($template, [
            '{{ aspect_ratio }}' => $canvas['aspect_ratio'],
            '{{ style }}' => $this->joinSentences([
                $style['theme'],
                $style['background'],
                $style['color_mode'],
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
