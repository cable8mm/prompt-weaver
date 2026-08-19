<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Contracts\PromptInterface;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use RuntimeException;

class DesignBriefPrompt implements PromptInterface
{
    /**
     * Random creativity seed pool that can be mixed regardless of category.
     * Can be subdivided into category-specific pools if needed.
     */
    private array $moodSeeds = [
        'futuristic and space-themed',
        'retro 80s neon',
        'minimal Scandinavian',
        'botanical and earthy',
        'Japanese wabi-sabi',
        'art deco geometric',
        'playful pop art',
        'industrial concrete texture',
        'watercolor and pastel',
        'monochrome brutalist',
    ];

    private array $seasonSeeds = [
        'spring cherry blossom mood',
        'summer beach and citrus mood',
        'autumn amber and maple mood',
        'winter frost and pine mood',
        'no specific season, timeless mood',
    ];

    private array $textureSeeds = [
        'subtle grid pattern',
        'organic hand-drawn line texture',
        'halftone dot pattern',
        'soft gradient mesh',
        'geometric line-art pattern',
        'paper/craft texture',
    ];

    private ?string $lastMoodSeed = null;

    private ?string $lastSeasonSeed = null;

    private ?string $lastTextureSeed = null;

    private ?string $promptString = null;

    private mixed $response = null;

    /**
     * @param  Category  $category  design brief category
     * @param  Format  $format  design brief format
     * @param  ColorMode  $colorMode  color output mode
     */
    public function __construct(
        private Category $category,
        private Format $format,
        public string $color = 'black-and-white',
        private ColorMode $colorMode = ColorMode::MONO,
    ) {}

    public function build(): void
    {
        $randomSeeds = implode(', ', [
            $this->pickRandom($this->moodSeeds, $this->lastMoodSeed),
            $this->pickRandom($this->seasonSeeds, $this->lastSeasonSeed),
            $this->pickRandom($this->textureSeeds, $this->lastTextureSeed),
        ]);

        $printingInstruction = $this->colorMode === ColorMode::MONO
            ? ($this->color === 'black-and-white'
                ? 'assume high-contrast monochrome/greyscale-safe design unless the random seeds clearly suggest a full-color context.'
                : "design for a black-and-white laser printer with high-contrast, greyscale-safe choices; use a color scheme based on {$this->color} only when it remains distinguishable in grayscale.")
            : "design for a color inkjet printer with strong contrast and bleed-safe details; use a color scheme based on {$this->color} unless the random seeds clearly suggest otherwise.";

        $template = file_get_contents(__DIR__.'/../stubs/design-brief.prompt');

        $this->promptString = strtr($template, [
            '{{ category }}' => $this->category->value,
            '{{ format }}' => $this->format->value,
            '{{ color_mode }}' => $this->colorMode->value,
            '{{ random_seeds }}' => $randomSeeds,
            '{{ printing_instruction }}' => $printingInstruction,
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
            throw new RuntimeException('Design brief response was not a JSON object.');
        }

        return $this->response;
    }

    public function response(): mixed
    {
        return $this->response;
    }

    private function pickRandom(array $pool, ?string &$lastPicked): string
    {
        $available = count($pool) > 1 && $lastPicked !== null
            ? array_values(array_diff($pool, [$lastPicked]))
            : $pool;

        $lastPicked = $available[array_rand($available)];

        return $lastPicked;
    }
}
