<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Contracts\PromptInterface;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\ContrastSeed;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\LayoutSeed;
use Cable8mm\PromptWeaver\Enums\MaterialSeed;
use Cable8mm\PromptWeaver\Enums\MoodSeed;
use Cable8mm\PromptWeaver\Enums\MotifSeed;
use Cable8mm\PromptWeaver\Enums\SeasonSeed;
use Cable8mm\PromptWeaver\Enums\TextureSeed;

class DesignBriefPrompt implements PromptInterface
{
    /**
     * Random creativity seed pool that can be mixed regardless of category.
     * Can be subdivided into category-specific pools if needed.
     */
    private array $moodSeeds;

    private array $seasonSeeds;

    private array $textureSeeds;

    private array $layoutSeeds;

    private array $motifSeeds;

    private array $materialSeeds;

    private array $contrastSeeds;

    private ?string $lastMoodSeed = null;

    private ?string $lastSeasonSeed = null;

    private ?string $lastTextureSeed = null;

    private ?string $lastLayoutSeed = null;

    private ?string $lastMotifSeed = null;

    private ?string $lastMaterialSeed = null;

    private ?string $lastContrastSeed = null;

    private ?string $promptString = null;

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
    ) {
        $this->moodSeeds = MoodSeed::keys();
        $this->seasonSeeds = SeasonSeed::keys();
        $this->textureSeeds = TextureSeed::keys();
        $this->layoutSeeds = LayoutSeed::keys();
        $this->motifSeeds = MotifSeed::keys();
        $this->materialSeeds = MaterialSeed::keys();
        $this->contrastSeeds = ContrastSeed::keys();
    }

    public function build(): void
    {
        $randomSeeds = implode(', ', [
            $this->pickRandom($this->moodSeeds, $this->lastMoodSeed),
            $this->pickRandom($this->seasonSeeds, $this->lastSeasonSeed),
            $this->pickRandom($this->textureSeeds, $this->lastTextureSeed),
            $this->pickRandom($this->layoutSeeds, $this->lastLayoutSeed),
            $this->pickRandom($this->motifSeeds, $this->lastMotifSeed),
            $this->pickRandom($this->materialSeeds, $this->lastMaterialSeed),
            $this->pickRandom($this->contrastSeeds, $this->lastContrastSeed),
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

    private function pickRandom(array $pool, ?string &$lastPicked): string
    {
        $available = count($pool) > 1 && $lastPicked !== null
            ? array_values(array_diff($pool, [$lastPicked]))
            : $pool;

        $lastPicked = $available[array_rand($available)];

        return $lastPicked;
    }
}
