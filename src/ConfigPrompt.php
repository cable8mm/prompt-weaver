<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Contracts\PromptInterface;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Cable8mm\PromptWeaver\Enums\PrintTarget;

class ConfigPrompt implements PromptInterface
{
    private ?string $promptString = null;

    /**
     * @param  string  $description  Template description describing the visual theme, background style, and mood
     * @param  string  $colorDirection  Primary color palette description (e.g., "warm brown and cream tones with soft gold accents")
     * @param  string  $fontMood  Typography feel description (e.g., "rounded handwritten-style Korean font")
     * @param  Format  $format  Template output format
     * @param  ColorMode  $colorMode  Color output mode
     * @param  string|null  $name  Optional template name (e.g., "벚꽃 아르데코"), used only as a reference tag, not a content source
     */
    public function __construct(
        private string $description,
        private string $colorDirection,
        private string $fontMood,
        private Format $format,
        private ?string $name = null,
        private ColorMode $colorMode = ColorMode::MONO,
        private Layout $layout = Layout::CENTERED,
    ) {}

    public function build(): void
    {
        $nameLine = $this->name !== null
            ? "- Name: {$this->name}\n"
            : '';
        $printTargets = implode(', ', array_map(
            fn (string $value): string => '"'.$value.'"',
            PrintTarget::keys(),
        ));

        $template = file_get_contents(__DIR__.'/../stubs/config.'.$this->layout->value.'.prompt');

        $this->promptString = strtr($template, [
            '{{ name_line }}' => $nameLine,
            '{{ description }}' => $this->description,
            '{{ color_direction }}' => $this->colorDirection,
            '{{ font_mood }}' => $this->fontMood,
            '{{ aspect_ratio }}' => $this->format->ratio(),
            '{{ color_mode }}' => $this->colorMode->value,
            '{{ print_targets }}' => $printTargets,
        ]);
    }

    public function prompt(): ?string
    {
        return $this->promptString;
    }
}
