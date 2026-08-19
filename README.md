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
- Composer 2.x

## Installation

```bash
composer require cable8mm/prompt-weaver
```

To work on this repository locally, install the development dependencies instead:

```bash
git clone https://github.com/cable8mm/prompt-weaver.git
cd prompt-weaver
composer install
```

## What It Does

The library contains three prompt builders:

- `Cable8mm\PromptWeaver\DesignBriefPrompt`
- `Cable8mm\PromptWeaver\ConfigPrompt`
- `Cable8mm\PromptWeaver\ImagePrompt`

They work together like this:

1. `DesignBriefPrompt` takes a category and format in the constructor, then `build()` generates a Wi-Fi signage design brief prompt and `prompt()` returns it.
2. `ConfigPrompt` takes the design brief text, color direction, and font mood in the constructor, then `build()` generates the prompt and `prompt()` returns it.
3. `ImagePrompt` takes the parsed JSON config in the constructor, then `build()` generates the prompt and `prompt()` returns it.
4. All three implement `PromptInterface` with `execute(Client $client)` to send the prompt to an AI and `response()` to retrieve the result.

The final prompt text is also stored in the fixture example at
[`tests/Fixtures/cafe-restaurant/image.prompt`](tests/Fixtures/cafe-restaurant/image.prompt).

## Usage

### Step 1: Build a design brief prompt

Input:

```php
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;

$briefPrompt = new DesignBriefPrompt(
    category: Category::CAFE_RESTAURANT,
    format: Format::A45_POSTER,
);
$briefPrompt->build();
$promptText = $briefPrompt->prompt();
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

// This would usually be the model's response to Step 1.
$designBrief = 'A cozy cafe-style Wi-Fi sign with warm cream and coffee-brown tones.';

$configPrompt = new ConfigPrompt(
    designBrief: $designBrief,
    colorDirection: 'warm brown and cream tones with soft gold accents',
    fontMood: 'rounded handwritten-style Korean font',
    name: '카페 시그니처', // optional
);
$configPrompt->build();
$promptText = $configPrompt->prompt();
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

$imagePrompt = new ImagePrompt($config);
$imagePrompt->build();
$promptText = $imagePrompt->prompt();
```

## CLI Workflow

The repository includes the `./weaver` wrapper, which invokes `bin/prompt-weaver`:

```bash
./weaver --help
./weaver --version
```

You can also invoke the PHP entry point directly:

```bash
php bin/prompt-weaver --help
```

The CLI uses one template code to identify a fixture. The checked-in example uses `cafe-restaurant`. The provider and model are runtime options used by `pipe`, not part of the template code.

All commands below operate on the same fixture folder:

```bash
./weaver init cafe-restaurant
```

You can also specify the category and format when creating a fixture:

```bash
./weaver init cafe-restaurant --category="Office/Coworking" --format="Business Card"
```

When run from a terminal, `init` interactively prompts you to select a category and format if you omit `--category` / `--format`. The available categories are `Cafe/Restaurant`, `Office/Coworking`, `Stay/Hotel`, `Event/Exhibition`, and `Other`; the available formats are `A4/A5 Poster`, `L-Stand/Table Tent`, `Sticker`, and `Business Card`. In non-interactive environments (tests, CI, pipes), the defaults are used automatically.

The fixture reference is positional for commands such as `brief`, `config`, `image`, `preview`, and `calibrate`. `preview` and `calibrate` also accept a direct fixture directory with `--fixture=/path/to/fixture`.

This creates:

```text
tests/Fixtures/cafe-restaurant/manifest.json
```

The commands use the files created or saved in that folder:

```bash
./weaver brief cafe-restaurant
./weaver config cafe-restaurant
./weaver image cafe-restaurant
./weaver calibrate cafe-restaurant
./weaver preview cafe-restaurant
```

`brief` reads `manifest.json`, prints the generated prompt, and saves it as `brief.prompt`. `config` reads `design-brief.json`, prints the generated prompt, and saves it as `config.prompt`. `image` reads `config.json`, prints the generated prompt, and saves it as `image.prompt`. `calibrate` detects the actual white text boxes and QR frame in `image.png`, then writes calibrated coordinates to `calibrate.config.json` without changing `config.json`. `preview` uses `calibrate.config.json` when it exists, otherwise it uses `config.json`; its output format is selected by the output filename extension.

What each command outputs:

1. `brief` prints the design-brief prompt you send to a model.
2. `config` prints the JSON-generation prompt you send after you have a design brief result.
3. `image` prints the final image-generation prompt you can paste into your image model.
4. `calibrate` writes `calibrate.config.json` to match the actual text-box and QR-frame positions in `image.png`.
5. `preview` renders a human-checkable `preview.png` or browser-based `preview.html` on top of the fixture background using `calibrate.config.json` when available.
6. `chain` prints all three prompts in one run for quick inspection.
7. `init` creates a new fixture manifest folder with the template `code` and default values for `category` and `format`, or with the values you pass via `--category` and `--format`.
8. `pipe` runs the full three-step pipeline end-to-end by sending each prompt to an AI model via `cable8mm/nano-ai` and printing all prompts and intermediate JSON responses. The default provider is `openrouter` with the `google/gemma-4-26b-a4b-it:free` model; use `--provider=openai` to switch to OpenAI.

For the complete command and option list, run `./weaver --help` or `./weaver list`.

## Output Flow

This package is intended to be used as part of a multi-step generation pipeline:

### Manual workflow

1. Create a `DesignBriefPrompt` with category and format, call `build()`, then retrieve the prompt via `prompt()`.
2. Send that prompt to a model and capture the brief text.
3. Create a `ConfigPrompt` with the design brief, color direction, font mood, and optional template name, call `build()`, then retrieve the prompt via `prompt()`.
4. Send that prompt to a model and parse the returned JSON.
5. Create an `ImagePrompt` with the parsed config, call `build()`, then retrieve the prompt via `prompt()`.
6. Send the final text to your image model or image generator.
7. (Optional) Call `execute(Client $client)` on any prompt class to send the prompt to an AI model, then `response()` to get the result.

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
- The `tests/Fixtures/cafe-restaurant/` folder shows one complete example of the chain, including the final image prompt.

## Testing

The easiest way to test this package is to create one fixture and keep all generated files in its folder, then run Pest.

### 1) Create a fixture folder

```bash
./weaver init cafe-restaurant
```

This creates `tests/Fixtures/cafe-restaurant/manifest.json` with the template `code` and default values for `category` and `format`. You can also pass `--category` and `--format` to customize the manifest:

```bash
./weaver init cafe-restaurant --category="Office/Coworking" --format="Business Card"
```

When run from a terminal, `init` interactively prompts you to select a category and format if you omit `--category` / `--format`. In non-interactive environments (tests, CI, pipes), the defaults are used automatically. Run `./weaver --help` to see the available categories and formats.

### 2) Generate the design-brief prompt

```bash
./weaver brief cafe-restaurant
```

The prompt is also saved automatically as `tests/Fixtures/cafe-restaurant/brief.prompt`. Send it to a model and save its JSON response as `tests/Fixtures/cafe-restaurant/design-brief.json`.

### 3) Generate the config prompt

```bash
./weaver config cafe-restaurant
```

The command reads `design-brief.json`, takes its `design_brief` value, prints the JSON-generation prompt, and saves it as `tests/Fixtures/cafe-restaurant/config.prompt`. Send that prompt to a model and save its JSON response as `tests/Fixtures/cafe-restaurant/config.json`.

### 4) Generate the final image prompt

```bash
./weaver image cafe-restaurant
```

The command reads `config.json`, prints the final image-generation prompt, and saves it as `tests/Fixtures/cafe-restaurant/image.prompt`.

### 5) Calibrate the config to the generated image

```bash
./weaver calibrate cafe-restaurant
```

This detects the actual white text boxes and QR frame in `image.png`. It writes the calibrated SSID/password `box_y_pc` values and QR `x_pc`, `y_pc`, and `width_pc` values to `calibrate.config.json`, leaving the original `config.json` unchanged.

### 6) Generate a preview image

```bash
./weaver preview cafe-restaurant
```

This creates `tests/Fixtures/cafe-restaurant/preview.png` using `calibrate.config.json` when present, so you can inspect the SSID, password, and QR placement by eye. If `config.json` changes, run `calibrate` again to regenerate the calibrated config.

### 7) Generate a browser preview

```bash
./weaver preview cafe-restaurant --output=html
```

This creates `tests/Fixtures/cafe-restaurant/preview.html` using `image.png`, `calibrate.config.json`, and `fonts/AtkinsonHyperlegible-Regular.woff2` as external files. The HTML reads the SSID and password values from the JSON and renders the QR code in the calibrated position. The QR image is embedded in the HTML, so no additional JavaScript QR library is required. Keep the generated HTML in its fixture directory so its relative asset paths remain valid.

Because browsers commonly block `fetch()` from local `file://` pages, serve the fixture directory through a local web server before opening the HTML:

```bash
php -S localhost:8000 -t tests/Fixtures/cafe-restaurant
```

Then open <http://localhost:8000/preview.html>. If `config.json` changes, run `calibrate` and regenerate `preview.html` so the calibrated coordinates and QR payload are refreshed.

### 8) Run the automated pipeline

If you have an OpenRouter API key, you can run the full three-step pipeline automatically. The default provider is `openrouter` with the `google/gemma-4-26b-a4b-it:free` model:

```bash
./weaver pipe cafe-restaurant --api-key=sk-or-v1-...
```

Or with explicit options:

```bash
./weaver pipe \
  --category="Cafe/Restaurant" \
  --format="A4/A5 Poster" \
  --provider=openrouter \
  --api-key=sk-or-v1-... \
  --model=google/gemma-4-26b-a4b-it:free \
  --color="warm brown and cream"
```

To use OpenAI instead, pass `--provider=openai` and an OpenAI API key:

```bash
./weaver pipe cafe-restaurant --provider=openai --api-key=sk-... --model=gpt-4o-mini
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

- `tests/Fixtures/cafe-restaurant/manifest.json`
- `tests/Fixtures/cafe-restaurant/design-brief.json`
- `tests/Fixtures/cafe-restaurant/config.json`
- `tests/Fixtures/cafe-restaurant/image.prompt`

The integration test reads those files and checks that:

1. The design-brief prompt is generated correctly.
2. The config prompt includes the generated brief.
3. The image prompt matches the saved `image.prompt` fixture.
4. The preview image can be generated from the fixture background without errors, with credential text and the QR code rendered in the calibrated area.

### 9.5) E2E test fixtures

The repo also includes fixtures generated from real OpenRouter API calls:

- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/manifest.json`
- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/brief.prompt`
- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/design-brief.json`
- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/config.prompt`
- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/config.json`
- `tests/Fixtures/google-gemma-4-26b-a4b-it-free/image.prompt`

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
