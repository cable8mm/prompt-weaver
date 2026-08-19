<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;

/**
 * Orchestrates the three-step prompt chain (design brief → config → image prompt)
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
     * Runs the full prompt pipeline.
     *
     * 1. Builds a design-brief prompt and sends it to the model → receives a design-brief JSON.
     * 2. Builds a config prompt from that brief and sends it to the model → receives a config JSON.
     * 3. Builds the final image-generation prompt from the parsed config.
     *
     * @param  Category  $category  The category enum
     * @param  Format  $format  The format enum
     * @param  string|null  $color  Optional color direction passed to DesignBriefPrompt.
     * @return PipeResult Contains all three prompts plus the parsed intermediate JSON.
     */
    public function run(Category $category, Format $format, ?string $color = null): PipeResult
    {
        // Step 1 — design brief
        $briefPrompt = new DesignBriefPrompt(
            category: $category,
            format: $format,
            color: $color ?? 'black-and-white',
        );
        $briefPrompt->build();
        $briefJson = $briefPrompt->execute($this->client);

        $description = $briefJson['description']
            ?? throw new \RuntimeException('Design brief response missing "description" field.');
        $colorDirection = $briefJson['color_direction']
            ?? throw new \RuntimeException('Design brief response missing "color_direction" field.');
        $fontMood = $briefJson['font_mood']
            ?? throw new \RuntimeException('Design brief response missing "font_mood" field.');
        $name = $briefJson['name'] ?? null;

        // Step 2 — config JSON
        $configPrompt = new ConfigPrompt(
            description: $description,
            colorDirection: $colorDirection,
            fontMood: $fontMood,
            format: $format,
            name: $name,
        );
        $configPrompt->build();
        $config = $configPrompt->execute($this->client);

        // Step 3 — final image prompt (build only, execution is left to the caller)
        $imagePrompt = new ImagePrompt($config);
        $imagePrompt->build();

        return new PipeResult(
            briefPrompt: $briefPrompt->prompt() ?? '',
            briefJson: $briefJson,
            configPrompt: $configPrompt->prompt() ?? '',
            config: $config,
            imagePrompt: $imagePrompt->prompt() ?? '',
        );
    }
}
