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

The CLI uses one fixture reference in the form `model/scenario`. All commands below operate on the same fixture folder:

```bash
./weaver init chatgpt/cafe-restaurant
```

This creates:

```text
tests/Fixtures/chatgpt/cafe-restaurant/manifest.json
```

The commands use the files created or saved in that folder:

```bash
./weaver brief chatgpt/cafe-restaurant
./weaver config chatgpt/cafe-restaurant
./weaver image chatgpt/cafe-restaurant
./weaver calibrate chatgpt/cafe-restaurant
./weaver preview chatgpt/cafe-restaurant
```

`brief` reads `manifest.json`, prints the generated prompt, and saves it as `brief.prompt`. `config` reads `design-brief.json`, prints the generated prompt, and saves it as `config.prompt`. `image` reads `config.json`, prints the generated prompt, and saves it as `image.prompt`. `calibrate` detects the actual white text boxes and QR frame in `image.png`, then writes calibrated coordinates to `calibrate.config.json` without changing `config.json`. `preview` uses `calibrate.config.json` when it exists, otherwise it uses `config.json`; its output format is selected by the output filename extension.

What each command outputs:

1. `brief` prints the design-brief prompt you send to a model.
2. `config` prints the JSON-generation prompt you send after you have a design brief result.
3. `image` prints the final image-generation prompt you can paste into your image model.
4. `calibrate` writes `calibrate.config.json` to match the actual text-box and QR-frame positions in `image.png`.
5. `preview` renders a human-checkable `preview.png` or browser-based `preview.html` on top of the fixture background using `calibrate.config.json` when available.
6. `chain` prints all three prompts in one run for quick inspection.
7. `init` creates a new fixture manifest folder with default values for `product`, `category`, and `format`.
8. `pipe` runs the full three-step pipeline end-to-end by sending each prompt to an AI model via `cable8mm/nano-ai` and printing all prompts and intermediate JSON responses.

## Output Flow

This package is intended to be used as part of a multi-step generation pipeline:

### Manual workflow

1. Call `DesignBriefPrompt::build()` to create the prompt for the brief-generation model.
2. Send that prompt to a model and capture the brief text.
3. Pass the brief text into `ConfigPrompt::build()` to create the JSON-generation prompt.
4. Send that prompt to a model and parse the returned JSON.
5. Pass the parsed JSON into `ImagePrompt::build()` to create the final image prompt.
6. Send the final text to your image model or image generator.

### Automated workflow with `pipe`

The `Pipe` class automates the entire three-step pipeline by sending each prompt to an AI model via `cable8mm/nano-ai`:

```php
use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;

$client = new Client(
    provider: 'openai',
    apiKey: 'sk-your-api-key',
    model: 'gpt-4o-mini',
);

$pipe = new Pipe($client);
$result = $pipe->run(
    product: 'a Wi-Fi signage template',
    category: Category::CAFE_RESTAURANT,
    format: Format::A45_POSTER,
    color: 'warm brown and cream', // optional
);

// Access all prompts and responses
echo $result->briefPrompt;   // Design brief prompt
echo $result->briefJson;     // Parsed design brief JSON
echo $result->configPrompt;  // Config generation prompt
echo $result->config;        // Parsed config JSON
echo $result->imagePrompt;   // Final image generation prompt
```

The `PipeResult` object contains all three prompts plus the parsed intermediate JSON responses, making it easy to inspect or log each step of the pipeline.

## Notes

- `DesignBriefPrompt` intentionally adds a small amount of randomness so the generated briefs feel less repetitive.
- `ConfigPrompt` is strict about JSON structure so the next step can parse the output reliably.
- `ImagePrompt` focuses on layout, contrast, and print-safe composition.
- The `tests/Fixtures/gpt-54-mini/wifi-note-cafe/` folder shows one complete example of the chain, including the final image prompt.

## Testing

The easiest way to test this package is to create one fixture and keep all generated files in its folder, then run Pest.

### 1) Create a fixture folder

```bash
./weaver init chatgpt/cafe-restaurant
```

This creates `tests/Fixtures/chatgpt/cafe-restaurant/manifest.json` with default values for `product`, `category`, and `format`.

### 2) Generate the design-brief prompt

```bash
./weaver brief chatgpt/cafe-restaurant
```

The prompt is also saved automatically as `tests/Fixtures/chatgpt/cafe-restaurant/brief.prompt`. Send it to a model and save its JSON response as `tests/Fixtures/chatgpt/cafe-restaurant/design-brief.json`.

### 3) Generate the config prompt

```bash
./weaver config chatgpt/cafe-restaurant
```

The command reads `design-brief.json`, takes its `design_brief` value, prints the JSON-generation prompt, and saves it as `tests/Fixtures/chatgpt/cafe-restaurant/config.prompt`. Send that prompt to a model and save its JSON response as `tests/Fixtures/chatgpt/cafe-restaurant/config.json`.

### 4) Generate the final image prompt

```bash
./weaver image chatgpt/cafe-restaurant
```

The command reads `config.json`, prints the final image-generation prompt, and saves it as `tests/Fixtures/chatgpt/cafe-restaurant/image.prompt`.

### 5) Calibrate the config to the generated image

```bash
./weaver calibrate chatgpt/cafe-restaurant
```

This detects the actual white text boxes and QR frame in `image.png`. It writes the calibrated SSID/password `box_y_pc` values and QR `x_pc`, `y_pc`, and `width_pc` values to `calibrate.config.json`, leaving the original `config.json` unchanged.

### 6) Generate a preview image

```bash
./weaver preview chatgpt/cafe-restaurant
```

This creates `tests/Fixtures/chatgpt/cafe-restaurant/preview.png` using `calibrate.config.json` when present, so you can inspect the SSID, password, and QR placement by eye. If `config.json` changes, run `calibrate` again to regenerate the calibrated config.

### 7) Generate a browser preview

```bash
./weaver preview chatgpt/cafe-restaurant --output=html
```

This creates `tests/Fixtures/chatgpt/cafe-restaurant/preview.html` using `image.png`, `calibrate.config.json`, and `fonts/AtkinsonHyperlegible-Regular.woff2` as external files. The HTML reads the SSID and password values from the JSON and renders the QR code in the calibrated position. The QR image is embedded in the HTML, so no additional JavaScript QR library is required. Keep the generated HTML in its fixture directory so its relative asset paths remain valid.

Because browsers commonly block `fetch()` from local `file://` pages, serve the fixture directory through a local web server before opening the HTML:

```bash
php -S localhost:8000 -t tests/Fixtures/chatgpt/cafe-restaurant
```

Then open <http://localhost:8000/preview.html>. If `config.json` changes, run `calibrate` and regenerate `preview.html` so the calibrated coordinates and QR payload are refreshed.

### 8) Run the automated pipeline

If you have an OpenAI API key, you can run the full three-step pipeline automatically:

```bash
./weaver pipe chatgpt/cafe-restaurant --api-key=sk-your-api-key
```

Or with explicit options:

```bash
./weaver pipe \
  --product="a Wi-Fi signage template" \
  --category="Cafe/Restaurant" \
  --format="A4/A5 Poster" \
  --provider=openai \
  --api-key=sk-your-api-key \
  --model=gpt-4o-mini \
  --color="warm brown and cream"
```

This command:

1. Generates the design-brief prompt and sends it to the model
2. Parses the design-brief JSON response
3. Generates the config prompt and sends it to the model
4. Parses the config JSON response
5. Generates the final image prompt
6. Prints all prompts and intermediate JSON responses

### 9) Compare against fixtures

The repo already includes one complete example:

- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/manifest.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/design-brief.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/config.json`
- `tests/Fixtures/gpt-54-mini/wifi-note-cafe/image.txt`

The integration test reads those files and checks that:

1. The design-brief prompt is generated correctly.
2. The config prompt includes the generated brief.
3. The image prompt matches the saved `image.txt` fixture.
4. The preview image can be generated from the fixture background without errors, with credential text and the QR code rendered in the calibrated area.

### 9.5) E2E test fixtures

The repo also includes fixtures generated from real OpenRouter API calls:

- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/manifest.json`
- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/brief.prompt`
- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/design-brief.json`
- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/config.prompt`
- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/config.json`
- `tests/Fixtures/openrouter/google-gemma-4-26b-a4b-it-free/image.prompt`

These fixtures are generated automatically when you run the E2E test:

```bash
composer test:e2e
```

The E2E test uses the OpenRouter API with the `google/gemma-4-26b-a4b-it:free` model and saves all prompts and responses to the fixtures directory for inspection and debugging.

### 10) Run the tests

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
