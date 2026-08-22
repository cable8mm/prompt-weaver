<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

/**
 * Orchestrates the three-step text prompt chain (design brief → config → image prompt)
 * by sending each generated prompt to a Laravel AI client.
 */
final class Pipe
{
    /**
     * @param  AiClient  $client  A configured Laravel AI client.
     */
    public function __construct(
        private readonly AiClient $client,
    ) {}

    /**
     * Runs the full text prompt pipeline. Image generation is external.
     *
     * 1. Builds a design-brief prompt and sends it to the model → receives a design-brief JSON.
     * 2. Builds a config prompt from that brief and sends it to the model → receives a config JSON.
     * 3. Builds the final image-generation prompt from the parsed config.
     *
     * @param  Category  $category  The category enum
     * @param  Format  $format  The format enum
     * @param  ColorMode  $colorMode  Color output mode
     * @param  Layout  $layout  The config layout
     * @param  string|null  $color  Optional color direction passed to DesignBriefPrompt.
     * @return PipeResult Contains all three prompts plus the parsed intermediate JSON.
     */
    public function run(
        Category $category,
        Format $format,
        ?string $color = null,
        ColorMode $colorMode = ColorMode::MONO,
        Layout $layout = Layout::CENTERED,
        ?callable $onProgress = null,
        ?string $provider = null,
        ?string $model = null,
    ): PipeResult {
        // Step 1 — design brief
        if ($onProgress !== null) {
            $onProgress('brief', 'Generating design brief...');
        }
        $briefPrompt = new DesignBriefPrompt(
            category: $category,
            format: $format,
            colorMode: $colorMode,
            color: $color ?? 'black-and-white',
        );
        $briefPrompt->build();
        $briefJson = $this->client->structured(
            $briefPrompt->prompt() ?? throw new \RuntimeException('Unable to build design brief prompt.'),
            self::briefSchema(...),
            $provider,
            $model,
        );
        if ($onProgress !== null) {
            $onProgress('brief.complete', 'Design brief received.');
        }

        $description = $briefJson['description']
            ?? throw new \RuntimeException('Design brief response missing "description" field.');
        $colorDirection = $briefJson['color_direction']
            ?? throw new \RuntimeException('Design brief response missing "color_direction" field.');
        $fontMood = $briefJson['font_mood']
            ?? throw new \RuntimeException('Design brief response missing "font_mood" field.');
        $name = $briefJson['name'] ?? null;

        // Step 2 — config JSON
        if ($onProgress !== null) {
            $onProgress('config', 'Generating config JSON...');
        }
        $configPrompt = new ConfigPrompt(
            description: $description,
            colorDirection: $colorDirection,
            fontMood: $fontMood,
            format: $format,
            colorMode: $colorMode,
            name: $name,
            layout: $layout,
        );
        $configPrompt->build();
        $config = $this->client->structured(
            $configPrompt->prompt() ?? throw new \RuntimeException('Unable to build config prompt.'),
            self::configSchema(...),
            $provider,
            $model,
        );
        if ($onProgress !== null) {
            $onProgress('config.complete', 'Config JSON received.');
        }

        // Step 3 — final image prompt (build only, execution is left to the caller)
        if ($onProgress !== null) {
            $onProgress('image', 'Building image prompt...');
        }
        $imagePrompt = new ImagePrompt($config);
        $imagePrompt->build();
        if ($onProgress !== null) {
            $onProgress('image.complete', 'Pipeline complete.');
        }

        return new PipeResult(
            briefPrompt: $briefPrompt->prompt() ?? '',
            briefJson: $briefJson,
            configPrompt: $configPrompt->prompt() ?? '',
            config: $config,
            imagePrompt: $imagePrompt->prompt() ?? '',
        );
    }

    /**
     * @return array<string, Type>
     */
    private static function briefSchema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'description' => $schema->string()->required(),
            'color_direction' => $schema->string()->required(),
            'font_mood' => $schema->string()->required(),
        ];
    }

    /**
     * The config contract deliberately keeps nested content fields open-ended;
     * the renderer only requires the coordinates and semantic fields below.
     *
     * @return array<string, Type>
     */
    private static function configSchema(JsonSchema $schema): array
    {
        $coordinates = fn () => [
            'x_pc' => $schema->number()->required(),
            'y_pc' => $schema->number()->required(),
        ];

        $contentElement = $schema->object([
            ...$coordinates(),
            'text' => $schema->string(),
            'style' => $schema->string(),
            'width_pc' => $schema->number(),
        ]);
        $placeholder = $schema->object([
            'box_x_pc' => $schema->number()->required(),
            'box_y_pc' => $schema->number()->required(),
            'box_width_pc' => $schema->number()->required(),
            'box_height_pc' => $schema->number()->required(),
            'label' => $schema->string(),
            'label_position' => $schema->string(),
            'box_fill' => $schema->string(),
            'box_fill_note' => $schema->string(),
            'align' => $schema->string(),
            'font_family' => $schema->string(),
            'font_size_px' => $schema->number(),
            'font_weight' => $schema->string(),
            'color' => $schema->string(),
        ])->required();

        return [
            'canvas' => $schema->object([
                'width_pc' => $schema->number()->required(),
                'height_pc' => $schema->number()->required(),
                'aspect_ratio' => $schema->string()->required(),
            ])->required(),
            'style' => $schema->object([
                'theme' => $schema->string()->required(),
                'background' => $schema->string()->required(),
                'print_target' => $schema->string()->required(),
            ])->required(),
            // Content elements are layout-dependent. Known elements remain
            // schema-described, but none is mandatory; placeholders below
            // are the fixed contract consumed by the renderer.
            'content' => $schema->object([
                'title' => $contentElement,
                'wifi_icon' => $contentElement,
                'message' => $contentElement,
                'footer' => $contentElement,
            ])->required(),
            'placeholders' => $schema->object([
                'ssid' => $placeholder,
                'password' => $placeholder,
                'qr' => $schema->object([
                    'x_pc' => $schema->number()->required(),
                    'y_pc' => $schema->number()->required(),
                    'width_pc' => $schema->number()->required(),
                    'style' => $schema->string(),
                    'box_fill' => $schema->string(),
                    'box_fill_note' => $schema->string(),
                ])->required(),
            ])->required(),
        ];
    }
}
