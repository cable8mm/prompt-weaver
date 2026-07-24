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

1. `DesignBriefPrompt` takes a product, category, and format, then returns a prompt for generating a short design brief.
2. `ConfigPrompt` takes that design brief text, then returns a prompt for generating strict JSON.
3. `ImagePrompt` takes the parsed JSON config, then returns the final image-generation prompt you can paste into a model.

The final prompt text is also stored in the fixture example at
[`tests/Fixtures/gpt-54-mini/wifi-note-cafe/image.txt`](/Users/cable8mm/Herd/prompt-weaver/tests/Fixtures/gpt-54-mini/wifi-note-cafe/image.txt).

## Usage

### Step 1: Build a design brief prompt

Input:

```php
$briefPrompt = new DesignBriefPrompt(product: 'a Wi-Fi signage template');
$promptText = $briefPrompt->build(
    category: 'Cafe/Restaurant',
    format: 'A4/A5 Poster'
);
```

Output:

```text
[Role]
You are a creative director for a Wi-Fi signage template. Your job is to write ONE short design brief...
...
[Inputs]
- Category: Cafe/Restaurant
- Format: A4/A5 Poster
...
```

That returned text is not the final design brief yet. It is the prompt you send to a model.

### Step 2: Turn the design brief into a config prompt

```php
use Cable8mm\PromptWeaver\ConfigPrompt;

$configPrompt = new ConfigPrompt();

// This would usually be the model's response to Step 1.
$designBrief = 'A cozy cafe-style Wi-Fi sign with warm cream and coffee-brown tones.';

$promptText = $configPrompt->build(
    designBrief: $designBrief
);
```

Output:

```text
[Role]
You are a design-template config generator for a Wi-Fi signage print system called WiFi Note.
Your ONLY job is to output a single valid JSON object matching the schema below.
...
```

### Step 3: Turn the config JSON into the final image prompt

`ImagePrompt` expects a structured config array shaped like the JSON schema produced by `ConfigPrompt`.

```php
use Cable8mm\PromptWeaver\ImagePrompt;

$imagePrompt = new ImagePrompt();

// This would usually be the parsed JSON response from Step 2.
$config = [
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
];

$promptText = $imagePrompt->build($config);
```

## CLI Workflow

If you want to manually test prompts and copy the output into `tests/Fixtures/*`, use the CLI:

```bash
php bin/prompt-weaver init --model="gemini-54-flash" --scenario="wifi-warm-cafe-in-summer"
```

This creates:

```text
tests/Fixtures/gemini-54-flash/wifi-warm-cafe-in-summer/manifest.json
```

Then generate the prompts:

```bash
php bin/prompt-weaver brief --product="a Wi-Fi signage template" --category="Cafe/Restaurant" --format="A4/A5 Poster"
php bin/prompt-weaver config --brief="A cozy cafe-style Wi-Fi sign with warm cream and coffee-brown tones."
php bin/prompt-weaver image --config-file=tests/Fixtures/gpt-54-mini/wifi-note-cafe/config.json
```

What each command outputs:

1. `brief` prints the design-brief prompt you send to a model.
2. `config` prints the JSON-generation prompt you send after you have a design brief result.
3. `image` prints the final image-generation prompt you can paste into your image model.
4. `chain` prints all three prompts in one run for quick inspection.
5. `init` creates a new fixture manifest folder with default values for `product`, `category`, and `format`.

## Output Flow

This package is intended to be used as part of a multi-step generation pipeline:

1. Call `DesignBriefPrompt::build()` to create the prompt for the brief-generation model.
2. Send that prompt to a model and capture the brief text.
3. Pass the brief text into `ConfigPrompt::build()` to create the JSON-generation prompt.
4. Send that prompt to a model and parse the returned JSON.
5. Pass the parsed JSON into `ImagePrompt::build()` to create the final image prompt.
6. Send the final text to your image model or image generator.

## Notes

- `DesignBriefPrompt` intentionally adds a small amount of randomness so the generated briefs feel less repetitive.
- `ConfigPrompt` is strict about JSON structure so the next step can parse the output reliably.
- `ImagePrompt` focuses on layout, contrast, and print-safe composition.
- The `tests/Fixtures/gpt-54-mini/wifi-note-cafe/` folder shows one complete example of the chain, including the final `image.txt` prompt.

## Testing

The easiest way to test this package is to run the CLI, copy the output into `tests/Fixtures/*`, and then run Pest.

### 1) Create a fixture folder

```bash
php bin/prompt-weaver init --model="gemini-54-flash" --scenario="wifi-warm-cafe-in-summer"
```

This creates a new `manifest.json` with default values.

### 2) Generate the design-brief prompt

```bash
php bin/prompt-weaver brief --product="a Wi-Fi signage template" --category="Cafe/Restaurant" --format="A4/A5 Poster"
```

Copy the output into a file such as `tests/Fixtures/gemini-54-flash/wifi-warm-cafe-in-summer/design-brief.json` if you want to compare model responses later.

### 3) Generate the config prompt

```bash
php bin/prompt-weaver config --brief="A cozy cafe-style Wi-Fi sign with warm cream and coffee-brown tones."
```

This is the prompt you paste into a model to get the JSON config response.

### 4) Generate the final image prompt

```bash
php bin/prompt-weaver image --config-file=tests/Fixtures/gpt-54-mini/wifi-note-cafe/config.json
```

Copy the output into `tests/Fixtures/gpt-54-mini/wifi-note-cafe/image.txt` if you want to preserve the exact prompt for that model run.

### 5) Compare against fixtures

The repo already includes one complete example:

- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/manifest.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/design-brief.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/config.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/image.txt`

The integration test reads those files and checks that:

1. The design-brief prompt is generated correctly.
2. The config prompt includes the generated brief.
3. The image prompt matches the saved `image.txt` fixture.

### 6) Run the tests

```bash
composer test
```

If you change prompt wording, update the fixture files first, then run the tests again.

## Development

```bash
composer install
composer test
composer lint
```

## License

MIT
