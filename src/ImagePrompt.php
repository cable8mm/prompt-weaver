<?php

namespace Cable8mm\PromptWeaver;

class ImagePrompt
{
    /**
     * @param  array  $config  wifi-note template config JSON (canvas, style, content, placeholders)
     * @return string Gemini Nano Banana image building prompt
     */
    public function build(array $config): string
    {
        $canvas = $config['canvas'];
        $style = $config['style'];
        $content = $config['content'];
        $placeholders = $config['placeholders'];

        $lines = [];

        $lines[] = '[Task] Generate a high-contrast Wi-Fi signage image ONLY. Do not output any text, explanation, or JSON — return the image only.';
        $lines[] = '';
        $lines[] = '[Canvas]';
        $lines[] = "- Portrait canvas, aspect ratio {$canvas['aspect_ratio']}.";
        $lines[] = '- Treat the canvas as a 0-100% grid on both axes (0% = top/left, 100% = bottom/right).';
        $lines[] = '';
        $lines[] = '[Style]';
        $lines[] = $this->joinSentences([
            $style['theme'],
            $style['background'],
            $style['print_target'],
        ]);
        $lines[] = '';
        $lines[] = '[Layout — place elements at these exact grid positions]';

        $step = 1;

        // Title
        $title = $content['title'];
        $lines[] = "{$step}. Title \"{$title['text']}\": centered at x={$title['x_pc']}%, y={$title['y_pc']}%. {$title['style']}.";
        $step++;

        // Wi-Fi icon
        $icon = $content['wifi_icon'];
        $lines[] = "{$step}. Wi-Fi icon: centered at x={$icon['x_pc']}%, y={$icon['y_pc']}%, width≈{$icon['width_pc']}% of canvas width. {$icon['style']}.";
        $step++;

        // placeholder boxes like SSID / PASSWORD
        foreach (['ssid', 'password'] as $key) {
            if (! isset($placeholders[$key])) {
                continue;
            }
            $box = $placeholders[$key];
            $lines[] = "{$step}. {$key} placeholder box: centered at x={$box['box_x_pc']}%, y={$box['box_y_pc']}%, box width≈{$box['box_width_pc']}%, height≈{$box['box_height_pc']}% of canvas.";
            $lines[] = "   - The box's INTERIOR FILL must be solid {$box['box_fill']} — {$box['box_fill_note']}.";
            $lines[] = "   - A small label \"{$box['label']}\" sits ".$this->describeLabelPosition($box['label_position']).' (against the surrounding background, not inside the white area).';
            $lines[] = "   - Nothing else is drawn inside the box — it stays empty and pure {$box['box_fill']}.";
            $step++;
        }

        // Message
        $message = $content['message'];
        $lines[] = "{$step}. Message \"{$message['text']}\": centered at x={$message['x_pc']}%, y={$message['y_pc']}%.";
        $step++;

        // QR
        $qr = $placeholders['qr'];
        $lines[] = "{$step}. QR placeholder: square area centered at x={$qr['x_pc']}%, y={$qr['y_pc']}%, width≈{$qr['width_pc']}% of canvas. {$qr['style']}.";
        $step++;

        // Footer
        $footer = $content['footer'];
        $lines[] = "{$step}. Footer \"{$footer['text']}\": centered at x={$footer['x_pc']}%, y={$footer['y_pc']}%.";

        $lines[] = '';
        $lines[] = '[Strict rules]';
        $lines[] = '- The SSID box, PASSWORD box, and QR square must each be a solid, flat white fill with sharp, clean edges — treat them as "cutout windows" in the surrounding background, not a stylized box with a white border.';
        $lines[] = '- Do not let the background pattern show through or bleed into these three white areas.';
        $lines[] = '- Do not render any text or QR code inside these three areas — leave them blank.';
        $lines[] = '- Final request: generate the finished image now, following every instruction above exactly.';
        $lines[] = '- Output the image only.';

        return implode("\n", $lines);
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
