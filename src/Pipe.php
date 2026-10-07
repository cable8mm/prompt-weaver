<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Cable8mm\PromptWeaver\Validators\ConfigValidator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Throwable;

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
        [$briefPrompt, $briefJson] = $this->runStage('design brief generation', function () use ($category, $format, $colorMode, $color, $provider, $model): array {
            $prompt = new DesignBriefPrompt(
                category: $category,
                format: $format,
                colorMode: $colorMode,
                color: $color ?? 'black-and-white',
            );
            $prompt->build();
            $response = $this->client->structured(
                $prompt->prompt() ?? throw new \RuntimeException('Unable to build design brief prompt.'),
                self::briefSchema(...),
                $provider,
                $model,
            );

            return [$prompt, $response];
        });

        if ($onProgress !== null) {
            $onProgress('brief.validate', 'Validating design brief response...');
        }
        [$description, $colorDirection, $fontMood, $name] = $this->runStage('design brief validation', function () use ($briefJson): array {
            return [
                $briefJson['description'] ?? throw new \RuntimeException('Design brief response missing "description" field.'),
                $briefJson['color_direction'] ?? throw new \RuntimeException('Design brief response missing "color_direction" field.'),
                $briefJson['font_mood'] ?? throw new \RuntimeException('Design brief response missing "font_mood" field.'),
                $briefJson['name'] ?? null,
            ];
        });
        if ($onProgress !== null) {
            $onProgress('brief.complete', 'Design brief received.');
        }

        // Step 2 — config JSON
        if ($onProgress !== null) {
            $onProgress('config', 'Generating config JSON...');
        }
        [$configPrompt, $config] = $this->runStage('config generation', function () use ($description, $colorDirection, $fontMood, $format, $colorMode, $name, $layout, $provider, $model): array {
            $prompt = new ConfigPrompt(
                description: $description,
                colorDirection: $colorDirection,
                fontMood: $fontMood,
                format: $format,
                colorMode: $colorMode,
                name: $name,
                layout: $layout,
            );
            $prompt->build();
            $response = $this->client->structured(
                $prompt->prompt() ?? throw new \RuntimeException('Unable to build config prompt.'),
                self::configSchema(...),
                $provider,
                $model,
            );

            return [$prompt, $response];
        });

        if ($onProgress !== null) {
            $onProgress('config.validate', 'Validating config response...');
        }
        $config = $this->runStage('config validation', function () use ($config, $format, $layout): array {
            $validatedConfig = $this->applyTypographyDefaults($config, $format);
            $this->validateConfig($validatedConfig);

            // Title, message, and footer are application-owned content, not AI-generated copy.
            unset($validatedConfig['content']['message'], $validatedConfig['content']['footer']);
            if ($layout === Layout::MINI_SQUARE) {
                unset($validatedConfig['content']['title']);
            } elseif (isset($validatedConfig['content']['title']) && is_array($validatedConfig['content']['title'])) {
                unset(
                    $validatedConfig['content']['title']['text'],
                    $validatedConfig['content']['title']['x_pc'],
                    $validatedConfig['content']['title']['y_pc'],
                    $validatedConfig['content']['title']['align'],
                );
            }

            return $validatedConfig;
        });
        if ($onProgress !== null) {
            $onProgress('config.complete', 'Config JSON received.');
        }

        // Step 3 — final image prompt (build only, execution is left to the caller)
        if ($onProgress !== null) {
            $onProgress('image', 'Building image prompt...');
        }
        $imagePrompt = $this->runStage('image prompt generation', function () use ($config, $layout): ImagePrompt {
            $prompt = new ImagePrompt($config, $layout);
            $prompt->build();

            return $prompt;
        });
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
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function runStage(string $stage, callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $exception) {
            throw new PipeStageException($stage, $exception);
        }
    }

    /**
     * Validate the model's config response before passing it to the renderer.
     *
     * @param  array<string, mixed>  $config
     */
    private function validateConfig(array $config): void
    {
        foreach (['canvas', 'style', 'content', 'placeholders'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                throw new \RuntimeException("Config response is missing required key [{$key}].");
            }
        }

        (new ConfigValidator)->validate($config);

        foreach (['ssid', 'password'] as $placeholderName) {
            $placeholder = $config['placeholders'][$placeholderName] ?? null;
            if (! is_array($placeholder)) {
                throw new \RuntimeException("Config response is missing required object [placeholders.{$placeholderName}].");
            }

            foreach (['label', 'label_position', 'box_fill', 'box_fill_note'] as $field) {
                if (! is_string($placeholder[$field] ?? null) || trim($placeholder[$field]) === '') {
                    throw new \RuntimeException("Config response is missing required field [placeholders.{$placeholderName}.{$field}].");
                }
            }
        }

        $qrPlaceholder = $config['placeholders']['qr'] ?? null;
        if (! is_array($qrPlaceholder)) {
            throw new \RuntimeException('Config response is missing required object [placeholders.qr].');
        }

        if (! is_string($qrPlaceholder['style'] ?? null) || trim($qrPlaceholder['style']) === '') {
            throw new \RuntimeException('Config response is missing required field [placeholders.qr.style].');
        }
    }

    /**
     * Placeholder typography is a physical format contract, not AI output.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function applyTypographyDefaults(array $config, Format $format): array
    {
        $fontSizePt = $format->placeholderTypography()['font_size_pt'];

        foreach (['ssid', 'password'] as $placeholder) {
            if (! isset($config['placeholders'][$placeholder]) || ! is_array($config['placeholders'][$placeholder])) {
                continue;
            }

            $config['placeholders'][$placeholder]['font_size_pt'] = $fontSizePt;
            unset($config['placeholders'][$placeholder]['font_size_px']);
        }

        return $config;
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
            'label' => $schema->string()->required(),
            'label_position' => $schema->string()->required(),
            'box_fill' => $schema->string()->required(),
            'box_fill_note' => $schema->string()->required(),
            'align' => $schema->string(),
            'font_family' => $schema->string(),
            'font_weight' => $schema->string(),
            'color' => $schema->string(),
        ])->required();
        $titleElement = $schema->object([
            'style' => $schema->string(),
            'width_pc' => $schema->number(),
        ]);

        return [
            'canvas' => $schema->object([
                'width_pc' => $schema->number()->required(),
                'height_pc' => $schema->number()->required(),
                'aspect_ratio' => $schema->string()->required(),
                'width_mm' => $schema->number()->required(),
                'height_mm' => $schema->number()->required(),
                'dpi' => $schema->number()->required(),
            ])->required(),
            'style' => $schema->object([
                'theme' => $schema->string()->required(),
                'background' => $schema->string()->required(),
                'print_target' => $schema->string()->required(),
            ])->required(),
            'metadata' => $schema->object([
                'style' => $schema->object([
                    'theme' => $schema->string()->required(),
                    'background' => $schema->string()->required(),
                ])->required(),
            ])->required(),
            // Content elements are layout-dependent. Known elements remain
            // schema-described, but none is mandatory; placeholders below
            // are the fixed contract consumed by the renderer.
            'content' => $schema->object([
                'title' => $titleElement,
                'wifi_icon' => $contentElement,
            ])->required(),
            'placeholders' => $schema->object([
                'ssid' => $placeholder,
                'password' => $placeholder,
                'qr' => $schema->object([
                    'x_pc' => $schema->number()->required(),
                    'y_pc' => $schema->number()->required(),
                    'width_pc' => $schema->number()->required(),
                    'style' => $schema->string()->required(),
                    'box_fill' => $schema->string(),
                    'box_fill_note' => $schema->string(),
                ])->required(),
            ])->required(),
        ];
    }
}
