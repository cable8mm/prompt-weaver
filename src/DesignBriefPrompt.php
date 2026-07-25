<?php

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Enums\WifiNoteCategory;
use Cable8mm\PromptWeaver\Enums\WifiNoteFormat;

class DesignBriefPrompt
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

    /**
     * @param  string  $product  example "a print-signage product", "a wifi signage template", "a business card design", etc.
     */
    public function __construct(
        public string $product,
        public string $color = 'black-and-white',
    ) {}

    /**
     * Builds a design brief based on the provided category and format.
     *
     * @param  WifiNoteCategory  $category  design brief category
     * @param  WifiNoteFormat  $format  design brief format
     */
    public function build(WifiNoteCategory $category, WifiNoteFormat $format): string
    {
        $randomSeeds = implode(', ', [
            $this->pickRandom($this->moodSeeds, $this->lastMoodSeed),
            $this->pickRandom($this->seasonSeeds, $this->lastSeasonSeed),
            $this->pickRandom($this->textureSeeds, $this->lastTextureSeed),
        ]);

        $printingInstruction = $this->color === 'black-and-white'
            ? 'assume high-contrast monochrome/greyscale-safe design unless the random seeds clearly suggest a full-color context.'
            : "use a color scheme based on {$this->color} unless the random seeds clearly suggest otherwise.";

        $template = <<<PROMPT
[Role]
You are a creative director for {$this->product}. Your job is to write ONE short design brief for {$this->product}, based on the inputs below. Output ONLY a valid JSON object, no explanation, no markdown fences.

[Output schema]
{
  "concept_name": "<short catchy concept name, 2-6 words>",
  "design_brief": "<1-3 concise sentences describing the visual theme, background style, and mood — written so it can be dropped directly into an image-generation prompt>",
  "color_direction": "<primary color palette description, e.g. 'warm brown and cream tones with soft gold accents'>",
  "font_mood": "<short description of what typography feel fits, e.g. 'rounded handwritten-style Korean font'>"
}

[Inputs]
- Category: {$category->value}
- Format: {$format->value}
- Random creative seeds to incorporate (use these as inspiration, blend them naturally — do not just list them back): {$randomSeeds}

[Rules]
1. The brief must stay realistic and printable — avoid overly complex illustrations that won't reproduce well on a black-and-white laser printer if requested; {$printingInstruction}
2. Tailor the mood to the Category (e.g. Cafe/Restaurant → warm and inviting; Office/Coworking → clean and minimal; Stay/Hotel → calm and premium; Event/Exhibition → bold and energetic; Other → open interpretation).
3. Tailor the composition sensibility to the Format (e.g. A4/A5 Poster → can carry more visual detail/background pattern; L-Stand/Table Tent → compact, readable from an angle, less background clutter; Sticker → very simple, bold, single focal motif since it's small; Business Card → extremely minimal, mostly typographic).
4. Use the random creative seeds as flavor, not as a checklist — blend them into a coherent single concept rather than cramming all of them in.
5. "design_brief" must be written in English (it feeds an image-generation prompt), but "concept_name" stays in Korean.
6. Output must be valid, parseable JSON only.
PROMPT;

        return $template;
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
