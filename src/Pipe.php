<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;

/**
 * Orchestrates the three-step text prompt chain (design brief → config → image prompt)
 * by sending each generated prompt to an AI model via the NanoAI Client.
 */
final class Pipe
{
    /**
     * @param  Client  $client  A configured NanoAI client used for all generate() calls.
     */
    public function __construct(
        private readonly Client $client,
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
        $briefJson = $briefPrompt->execute($this->client);
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
        $config = $configPrompt->execute($this->client);
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
}
