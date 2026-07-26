<?php

namespace Cable8mm\PromptWeaver;

/**
 * Immutable value object holding the results of a Pipe::run() call.
 */
final class PipeResult
{
    /**
     * @param  string  $briefPrompt  The design-brief prompt sent to the model.
     * @param  array<string, mixed>  $briefJson  Parsed JSON response from the design-brief model.
     * @param  string  $configPrompt  The config-generation prompt sent to the model.
     * @param  array<string, mixed>  $config  Parsed config JSON (canvas, style, content, placeholders).
     * @param  string  $imagePrompt  The final image-generation prompt.
     */
    public function __construct(
        public readonly string $briefPrompt,
        public readonly array $briefJson,
        public readonly string $configPrompt,
        public readonly array $config,
        public readonly string $imagePrompt,
    ) {}
}
