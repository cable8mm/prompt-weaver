<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Contracts\PromptInterface;
use RuntimeException;

class ConfigPrompt implements PromptInterface
{
    private ?string $promptString = null;

    private mixed $response = null;

    /**
     * @param  string  $designBrief  User-provided design brief describing the visual theme, background style, and mood
     * @param  string  $colorDirection  Primary color palette description (e.g., "warm brown and cream tones with soft gold accents")
     * @param  string  $fontMood  Typography feel description (e.g., "rounded handwritten-style Korean font")
     * @param  string|null  $conceptName  Optional short concept label (e.g., "벚꽃 아르데코"), used only as a reference tag, not a content source
     */
    public function __construct(
        private string $designBrief,
        private string $colorDirection,
        private string $fontMood,
        private ?string $conceptName = null,
    ) {}

    public function build(): void
    {
        $conceptLine = $this->conceptName !== null
            ? "- Concept Name: {$this->conceptName}\n"
            : '';

        $template = file_get_contents(__DIR__.'/../stubs/config.prompt');

        $this->promptString = strtr($template, [
            '{{ concept_name_line }}' => $conceptLine,
            '{{ design_brief }}' => $this->designBrief,
            '{{ color_direction }}' => $this->colorDirection,
            '{{ font_mood }}' => $this->fontMood,
        ]);
    }

    public function prompt(): ?string
    {
        return $this->promptString;
    }

    public function execute(Client $client): mixed
    {
        $rawResponse = $client->generate($this->promptString ?? throw new RuntimeException('build() must be called before execute()'));

        // Strip markdown code fences if the model wrapped the JSON in them.
        $cleaned = preg_replace('/^```(?:json|php)?\s*\n(.*?)\n```\s*$/s', '$1', trim($rawResponse));

        $this->response = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($this->response)) {
            throw new RuntimeException('Config response was not a JSON object.');
        }

        return $this->response;
    }

    public function response(): mixed
    {
        return $this->response;
    }
}
