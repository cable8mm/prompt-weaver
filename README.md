# Prompt Weaver

[![code-style](https://github.com/cable8mm/prompt-weaver/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/prompt-weaver/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/prompt-weaver/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/prompt-weaver/actions/workflows/run-tests.yml)
![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/prompt-weaver/php)
![Packagist Version](https://img.shields.io/packagist/v/cable8mm/prompt-weaver)
![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/prompt-weaver)
![Packagist License](https://img.shields.io/packagist/l/cable8mm/prompt-weaver)

Prompt Weaver is a small PHP library for generating structured prompts for a Wi-Fi signage design workflow.

It helps you build three pieces of text:

1. A short creative brief
2. A JSON config prompt
3. A final image-generation prompt

The package is centered around the **WiFi Note** signage flow, where a design brief is turned into a printable, high-contrast sign layout.

## Requirements

- PHP 8.3 or newer

## Installation

```bash
composer require cable8mm/prompt-weaver
```

## What It Does

The library contains three prompt builders:

- `Cable8mm\PromptWeaver\DesignBriefPrompt`
- `Cable8mm\PromptWeaver\ConfigPrompt`
- `Cable8mm\PromptWeaver\ImagePrompt`

They work together like this:

1. `DesignBriefPrompt` creates a short design brief from a category and format.
2. `ConfigPrompt` turns that brief into a strict JSON-generation prompt.
3. `ImagePrompt` turns the resulting config into a detailed image prompt.

## Usage

### 1) Build a design brief prompt

```php
use Cable8mm\PromptWeaver\DesignBriefPrompt;

$briefPrompt = new DesignBriefPrompt(
    product: 'a Wi-Fi signage template'
);

$promptText = $briefPrompt->build(
    category: 'Cafe/Restaurant',
    format: 'A4/A5 Poster'
);
```

### 2) Build a config prompt

```php
use Cable8mm\PromptWeaver\ConfigPrompt;

$configPrompt = new ConfigPrompt();

$promptText = $configPrompt->build(
    designBrief: 'A warm cafe-style Wi-Fi sign with soft brown tones and a handwritten feel.'
);
```

### 3) Build an image prompt

`ImagePrompt` expects a structured config array shaped like the JSON schema produced by `ConfigPrompt`.

```php
use Cable8mm\PromptWeaver\ImagePrompt;

$imagePrompt = new ImagePrompt();

$promptText = $imagePrompt->build([
    'canvas' => [
        'aspect_ratio' => '3:4',
    ],
    'style' => [
        'theme' => 'Warm cafe vibe with a soft analog feel',
        'background' => 'cream paper texture with subtle grain',
        'print_target' => 'black-and-white laser printer safe',
    ],
    'content' => [
        'title' => [
            'text' => '와이파이 연결',
            'x_pc' => 50,
            'y_pc' => 10,
            'style' => 'bold and friendly',
        ],
        'wifi_icon' => [
            'x_pc' => 50,
            'y_pc' => 20,
            'width_pc' => 15,
            'style' => 'simple line icon',
        ],
        'message' => [
            'text' => '스캔하여 연결하세요.',
            'x_pc' => 50,
            'y_pc' => 62,
        ],
        'footer' => [
            'text' => '제작: WIFI NOTE',
            'x_pc' => 50,
            'y_pc' => 96,
        ],
    ],
    'placeholders' => [
        'ssid' => [
            'box_x_pc' => 50,
            'box_y_pc' => 40,
            'box_width_pc' => 70,
            'box_height_pc' => 8,
            'label' => 'SSID:',
            'label_position' => 'outside_above',
            'box_fill' => '#FFFFFF',
            'box_fill_note' => 'solid flat white cutout, no background pattern bleeding through',
        ],
        'password' => [
            'box_x_pc' => 50,
            'box_y_pc' => 52,
            'box_width_pc' => 70,
            'box_height_pc' => 8,
            'label' => 'PASSWORD:',
            'label_position' => 'outside_above',
            'box_fill' => '#FFFFFF',
            'box_fill_note' => 'solid flat white cutout, no background pattern bleeding through',
        ],
        'qr' => [
            'x_pc' => 50,
            'y_pc' => 80,
            'width_pc' => 28,
            'style' => 'QR frame style with clean edges',
        ],
    ],
]);
```

## Output Flow

This package is intended to be used as part of a multi-step generation pipeline:

1. Generate a design brief
2. Convert it into a config JSON prompt
3. Convert the config into a final image prompt
4. Send the prompt to your model or image generator

## Notes

- `DesignBriefPrompt` intentionally adds a small amount of randomness so the generated briefs feel less repetitive.
- `ConfigPrompt` is strict about JSON structure so the next step can parse the output reliably.
- `ImagePrompt` focuses on layout, contrast, and print-safe composition.

## Development

```bash
composer install
composer test
composer lint
```

## License

MIT
