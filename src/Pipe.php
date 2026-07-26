<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use RuntimeException;

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
     * @param  string  $product  e.g. "a Wi-Fi signage template"
     * @param  string|null  $color  Optional color direction passed to DesignBriefPrompt.
     * @return PipeResult Contains all three prompts plus the parsed intermediate JSON.
     */
    public function run(string $product, Category $category, Format $format, ?string $color = null): PipeResult
    {
        // Step 1 — design brief
        $briefPrompt = (new DesignBriefPrompt($product, $color ?? 'black-and-white'))
            ->build($category, $format);

        $briefResponse = $this->client->generate($briefPrompt);
        $briefJson = $this->parseJson($briefResponse, 'design brief');

        $designBrief = $briefJson['design_brief']
            ?? throw new RuntimeException('Design brief response missing "design_brief" field.');

        // Step 2 — config JSON
        $configPrompt = (new ConfigPrompt)->build($designBrief);

        $configResponse = $this->client->generate($configPrompt);
        $config = $this->parseJson($configResponse, 'config');

        // Step 3 — final image prompt
        $imagePrompt = (new ImagePrompt)->build($config);

        return new PipeResult(
            briefPrompt: $briefPrompt,
            briefJson: $briefJson,
            configPrompt: $configPrompt,
            config: $config,
            imagePrompt: $imagePrompt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $text, string $step): array
    {
        // Strip markdown code fences if the model wrapped the JSON in them.
        $cleaned = preg_replace('/^```(?:json|php)?\s*\n(.*?)\n```\s*$/s', '$1', trim($text));

        $decoded = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException("{$step} response was not a JSON object.");
        }

        return $decoded;
    }
}
