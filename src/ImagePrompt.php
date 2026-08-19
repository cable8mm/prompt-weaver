<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Contracts\PromptInterface;
use RuntimeException;

class ImagePrompt implements PromptInterface
{
    private ?string $promptString = null;

    private mixed $response = null;

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

        // Title
        $title = $content['title'];
        $layoutLines[] = "{$step}. Title \"{$title['text']}\": centered at x={$title['x_pc']}%, y={$title['y_pc']}%. {$title['style']}.";
        $step++;

        // Wi-Fi icon
        $icon = $content['wifi_icon'];
        $layoutLines[] = "{$step}. Wi-Fi icon: centered at x={$icon['x_pc']}%, y={$icon['y_pc']}%, width≈{$icon['width_pc']}% of canvas width. {$icon['style']}.";
        $step++;

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

    public function execute(Client $client): mixed
    {
        $this->response = $client->generate($this->promptString ?? throw new RuntimeException('build() must be called before execute()'));

        return $this->response;
    }

    public function response(): mixed
    {
        return $this->response;
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
