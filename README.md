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
- GD extension (required by preview, calibration, and QR rendering)
- `uv` (optional; required for OpenCV-based QR calibration)

## Installation

```bash
composer require cable8mm/prompt-weaver
```

### Laravel application setup

Laravel's package discovery registers the service provider automatically. If the
application uses Vite, include Prompt Weaver's stylesheet in the application's CSS
entry point, usually `resources/css/app.css`:

```css
@import "../../vendor/cable8mm/prompt-weaver/resources/css/prompt-weaver.css";
```

The stylesheet contains the Atkinson Hyperlegible font used by the browser preview.

Because its font URLs are relative, Vite includes and versions the font files in the application build. Build the frontend as usual:

```bash
npm run build
```

Apply the `prompt-weaver-font` class to dynamic SSID and password text rendered by the service:

```blade
<span class="prompt-weaver-font">{{ $ssid }}</span>
<span class="prompt-weaver-font">{{ $password }}</span>
```

No manual service-provider registration, Nova dependency, or `public/vendor` font
copy is required. The package's PHP/GD preview renderer uses its bundled TTF font
automatically.

For OpenCV-based QR calibration, install `uv` first. On macOS with Homebrew:

```bash
brew install uv
```

Or use the official installer on macOS/Linux:

```bash
curl -LsSf https://astral.sh/uv/install.sh | sh
```

After Composer installation, initialize the Python environment from the package directory:

```bash
uv sync --project vendor/cable8mm/prompt-weaver
```

This installs the locked Python dependencies from the package's `pyproject.toml` and `uv.lock` (currently `opencv-python-headless`). The package does not ask Composer to run network-dependent Python installation commands. `requirements.txt` is provided as a compatibility file for users who prefer a requirements-based workflow; the package's `uv` runner uses the project files above.

If `uv` is not installed, preview rendering still works, but calibration for fixtures with a QR placeholder requires `uv` and OpenCV.

### Service server setup

QR calibration runs a Python process from PHP. The server therefore needs `uv`, a writable cache directory, and permission for the PHP process to execute `proc_open()`. The first run downloads `opencv-python-headless`; later runs reuse the `uv` cache.

For a Linux server, run the following during deployment as the same user that runs the application (or PHP worker):

```bash
export UV_CACHE_DIR=/var/cache/prompt-weaver/uv
mkdir -p "$UV_CACHE_DIR"

cd /path/to/application
composer install --no-dev --prefer-dist --optimize-autoloader
uv sync --locked --project vendor/cable8mm/prompt-weaver
```

Give the PHP-FPM or queue-worker user read/write access to `UV_CACHE_DIR`. If the environment is managed by PHP-FPM or systemd, configure `UV_CACHE_DIR` there; setting it only in an interactive shell does not make it available to PHP.

The package does not install a service-specific CLI command. Call the package's calibration service from your application's command or job, and make that command fail when calibration fails. For example, if your service wraps calibration and preview in shell commands, use:

```bash
php artisan wifi:calibrate cafe-restaurant && php artisan wifi:preview cafe-restaurant
```

`calibrate` returns a non-zero exit code when `uv`, OpenCV, the image, or QR-frame detection fails. It does not write a new `config.json` on failure. Keep `uv.lock` in the deployed package; the PHP runner uses `uv run --locked` so a server cannot silently rewrite the lockfile.

For the package repository's GitHub Actions, install and cache `uv`, then install from the lockfile before running both Python and PHP tests:

```yaml
- uses: astral-sh/setup-uv@v10
  with:
    enable-cache: true
- run: uv sync --locked
- run: uv run --locked python scripts/test_calibrate_qr.py
- run: composer install --prefer-dist --no-progress
- run: composer test
```

The repository's `.github/workflows/run-tests.yml` follows this order.

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
2. `ConfigPrompt` takes the template description, color direction, and font mood in the constructor, then `build()` generates the prompt and `prompt()` returns it.
3. `ImagePrompt` takes the parsed JSON config in the constructor, then `build()` generates the prompt and `prompt()` returns it.
4. The prompt classes implement `PromptInterface` and only build prompt text. AI execution is handled by `Pipe` through Laravel AI.

The final prompt text is also stored in the fixture example at
[`tests/Fixtures/cafe-restaurant/image.prompt`](tests/Fixtures/cafe-restaurant/image.prompt).

## Usage

### Step 1: Build a design brief prompt

Input:

```php
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\ColorMode;

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
$description = 'A cozy cafe-style Wi-Fi sign with warm cream and coffee-brown tones.';

$configPrompt = new ConfigPrompt(
    description: $description,
    colorDirection: 'warm brown and cream tones with soft gold accents',
    fontMood: 'rounded handwritten-style Korean font',
    format: Format::A45_POSTER,
    colorMode: ColorMode::MONO,
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
        'aspect_ratio' => Format::A45_POSTER->ratio(),
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

### Iterative prompt-template workflow

The repository also includes editable prompt templates for working directly with a
chat-based AI. The shared image-preview instructions live in
[`prompts/preview.prompt`](prompts/preview.prompt), while layout-specific config
instructions live in [`stubs/`](stubs/).

To create or revise a layout config template:

1. Open [`prompts/config.prompt`](prompts/config.prompt) in a chat-based AI.
2. Include [`stubs/config.centered.prompt`](stubs/config.centered.prompt) as the
   canonical reference and ask the AI to create or revise exactly one
   `stubs/config.<layout>.prompt` file.
3. Save the result under `stubs/`, keeping the existing schema and changing only
   the layout-specific composition values.

For example, save an editorial layout as:

```text
stubs/config.editorial.prompt
```

To test that layout with an image-capable chat AI, run:

```bash
./weaver config-stub editorial
```

This inserts the complete `stubs/config.editorial.prompt` into the
`CONFIG PROMPT` section of [`prompts/preview.prompt`](prompts/preview.prompt) and
copies the assembled image prompt to the macOS clipboard. Paste it into the
interactive AI, inspect the generated image, then revise the stub and run the
command again.

To inspect or pipe the assembled prompt without using the clipboard, run:

```bash
./weaver config-stub editorial --print
```

The currently registered layout names are `centered`, `editorial`, `split`, and
`qr-focus`. A new layout name must also be registered in the CLI before it can be
used with `./weaver config-stub <layout>`.

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

All commands below operate on the same working folder under `.weaver`:

```bash
./weaver init cafe-restaurant
```

You can also specify the category and format when creating a fixture:

```bash
./weaver init cafe-restaurant --category="Office/Coworking" --format="A4/A5 Poster"
```

When run from a terminal, `init` interactively prompts you to select a category, format, and layout if you omit the corresponding options. Available layouts are `centered`, `editorial`, `split`, and `qr-focus`. In non-interactive environments (tests, CI, pipes), the defaults are used automatically. You can select one explicitly with `--layout=editorial`.

The fixture reference is positional for commands such as `brief`, `config`, `image`, `preview`, and `calibrate`. `config-stub` accepts a registered layout name and assembles an image prompt from `prompts/preview.prompt` and `stubs/config.<layout>.prompt`. `preview` and `calibrate` also accept a direct fixture directory with `--fixture=/path/to/fixture`.

This creates:

```text
.weaver/cafe-restaurant/manifest.json
```

`tests/Fixtures` contains checked-in reference data for the test suite. The CLI does not use it by default, so normal runs do not modify test fixtures.

The commands use the files created or saved in that folder:

```bash
./weaver brief cafe-restaurant
./weaver config cafe-restaurant
./weaver image cafe-restaurant
./weaver imagegen cafe-restaurant
./weaver calibrate cafe-restaurant
./weaver preview cafe-restaurant
```

`brief` reads `manifest.json` and saves the generated prompt as `brief.prompt`. `config` reads `design-brief.json`, takes its `description`, and saves the generated prompt as `config.prompt`. Save the model's response as `raw.config.json`. `image` reads `raw.config.json` and saves the generated prompt as `image.prompt`. `calibrate` detects the actual white text boxes and QR frame in `image.png`, then writes the calibrated final configuration to `config.json` without changing `raw.config.json`. `preview` uses `config.json` when it exists, otherwise it uses `raw.config.json`; its output format is selected by the output filename extension. `config-stub` assembles the interactive image prompt described above.

After the design has been generated, assign its final code from `config.json`'s `style.theme`:

```bash
./weaver code 202608202113
```

The command converts the theme to kebab-case, keeps at most the first four words and 48 characters, and removes a partial trailing word when the length limit is reached. For example, `Wabi-Sabi Minimalist` becomes `wabi-sabi-minimalist`. It renames the matching `.weaver/<code>` folder, updates `manifest.json`, and renames and updates `dist/<code>` when an export already exists. If the target code is already in use, the command stops without renaming anything.

What each command outputs:

1. `brief` saves the design-brief prompt you send to a model.
2. `config` saves the JSON-generation prompt you send after you have a template description.
3. `image` saves the final image-generation prompt you can paste into your image model.
4. `imagegen` reads `image.prompt` and saves the generated image as `image.png`.
5. `calibrate` writes the final `config.json` to match the actual text-box and QR-frame positions in `image.png`.
6. `preview` renders a human-checkable `preview.png` or browser-based `preview.html` for a fixture.
7. `chain` prints all three prompts in one run for quick inspection.
8. `init` creates a new fixture manifest folder with the template `code` and default values for `category`, `format`, and `color_mode`. Use `--color-mode=Color` for color output or `--color-mode=Mono` for monochrome output.
9. `code` renames a fixture from its current code to a kebab-case code derived from `config.json`'s `style.theme`. It updates the fixture folder, `manifest.json`, and any matching `dist/<code>` export.
10. `pipe` runs the full three-step pipeline end-to-end through `laravel/ai` and saves the prompts and intermediate JSON responses. Use `--show-output` to print them. The default provider is `openrouter` with the `google/gemma-4-26b-a4b-it:free` model; use `--provider=openai` to switch to OpenAI.
11. `export` packages a manually generated PNG and the working config into a Laravel-ready `dist/<code>` directory.
12. `config-stub` assembles a registered layout stub into the image-generation prompt and copies it to the clipboard for interactive AI testing. Use `--print` to print it instead.
13. `config:validate` validates a config JSON file against the required config structure and canvas aspect-ratio format.

For the complete command and option list, run `./weaver --help` or `./weaver list`.

## Output Flow

This package is intended to be used as part of a multi-step generation pipeline:

### Manual workflow

1. Create a `DesignBriefPrompt` with category and format, call `build()`, then retrieve the prompt via `prompt()`.
2. Send that prompt to a model and capture the description.
3. Create a `ConfigPrompt` with the description, color direction, font mood, and optional template name, call `build()`, then retrieve the prompt via `prompt()`.
4. Send that prompt to a model and parse the returned JSON.
5. Create an `ImagePrompt` with the parsed config, call `build()`, then retrieve the prompt via `prompt()`.
6. Send the final text to your image model or image generator.
7. Use the Laravel AI integration or `Pipe` to send generated prompts to an AI model.

### Automated workflow with `pipe`

The `Pipe` class automates the text-prompt portion of the pipeline by sending the design-brief and config prompts to an AI model via `laravel/ai`. It returns the final image-generation prompt. The `imagegen` command sends the saved `image.prompt` to a Laravel AI image provider:

```php
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Pipe;
use Cable8mm\PromptWeaver\Laravel\LaravelAiClient;

$pipe = new Pipe(new LaravelAiClient);
$result = $pipe->run(
    category: Category::CAFE_RESTAURANT,
    format: Format::A45_POSTER,
    color: 'warm brown and cream', // optional
    colorMode: ColorMode::MONO,
);

// Access all prompts and responses
echo $result->briefPrompt;   // Design brief prompt
echo json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); // Parsed design brief JSON
echo $result->configPrompt;  // Config generation prompt
echo json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);     // Parsed config JSON
echo $result->imagePrompt;   // Final image generation prompt
```

The `PipeResult` object contains all three prompts plus the parsed intermediate JSON responses, making it easy to inspect or log each step of the pipeline.

## Notes

- `DesignBriefPrompt` intentionally adds a small amount of randomness so the generated briefs feel less repetitive.
- `ConfigPrompt` is strict about JSON structure so the next step can parse the output reliably.
- `ImagePrompt` focuses on layout, contrast, and print-safe composition.
- The `tests/Fixtures/cafe-restaurant/` folder shows one complete example of the chain, including the final image prompt.

## Testing

The easiest way to test this package is to copy a checked-in fixture into a working directory, generate files there, then run Pest. The repository's reference fixtures remain unchanged.

### 1) Create a fixture folder

```bash
./weaver init cafe-restaurant
```

This creates `.weaver/cafe-restaurant/manifest.json` with the template `code` and default values for `category`, `format`, and `layout`. You can also pass `--category`, `--format`, and `--layout` to customize the manifest:

```bash
./weaver init cafe-restaurant --category="Office/Coworking" --format="A4/A5 Poster"
```

For example, select the editorial config layout with:

```bash
./weaver init cafe-restaurant --layout=editorial
```

When run from a terminal, `init` interactively prompts you to select a category and format if you omit `--category` / `--format`. In non-interactive environments (tests, CI, pipes), the defaults are used automatically. Run `./weaver --help` to see the available categories and formats.

### 2) Generate the design-brief prompt

```bash
./weaver brief cafe-restaurant
```

The prompt is also saved automatically as `.weaver/cafe-restaurant/brief.prompt`. Send it to a model and save its JSON response as `.weaver/cafe-restaurant/design-brief.json`.

### 3) Generate the config prompt

```bash
./weaver config cafe-restaurant
```

The command reads `design-brief.json`, takes its `description` value, and saves the JSON-generation prompt as `.weaver/cafe-restaurant/config.prompt`. Send that prompt to a model and save its JSON response as `.weaver/cafe-restaurant/raw.config.json`.

### 4) Generate the final image prompt

```bash
./weaver image cafe-restaurant
```

The command reads `raw.config.json` and saves the final image-generation prompt as `.weaver/cafe-restaurant/image.prompt`.

### 5) Calibrate the config to the generated image

```bash
./weaver calibrate cafe-restaurant
```

This detects the actual white text boxes and QR frame in `image.png`. It writes the calibrated SSID/password `box_y_pc` values and QR `x_pc`, `y_pc`, and `width_pc` values to `config.json`, leaving the original `raw.config.json` unchanged.

When available, QR frame calibration uses the optional Python/OpenCV detector for contour-based square detection. The detector is managed with `uv`:

```bash
uv run --project . scripts/calibrate_qr.py --help
```

The first `uv run` creates the cached environment from `pyproject.toml`; subsequent runs reuse it. QR calibration uses the Python detector exclusively. If `uv` or OpenCV is unavailable, `calibrate` reports an installation error instead of using a less accurate PHP detector. Set `PROMPT_WEAVER_UV` to select a different `uv` executable, `UV_CACHE_DIR` to a writable persistent cache directory on a server, or `PROMPT_WEAVER_PYTHON` to bypass `uv` and use a Python interpreter directly.

### 6) Generate a preview image

```bash
./weaver preview cafe-restaurant
```

This creates `.weaver/cafe-restaurant/preview.png` using the final `config.json` when present, so you can inspect the SSID, password, and QR placement by eye. If `raw.config.json` or `image.png` changes, run `calibrate` again to regenerate the final config.

### 7) Generate a browser preview

```bash
./weaver preview cafe-restaurant --output=html
```

This creates `.weaver/cafe-restaurant/preview.html` using `image.png`, `config.json`, and `fonts/AtkinsonHyperlegible-Regular.woff2` as external files. The HTML reads the SSID and password values from the JSON and renders the QR code in the calibrated position. The QR image is embedded in the HTML, so no additional JavaScript QR library is required. Keep the generated HTML in its working directory so its relative asset paths remain valid.

Because browsers commonly block `fetch()` from local `file://` pages, serve the fixture directory through a local web server before opening the HTML:

```bash
php -S localhost:8000 -t .weaver/cafe-restaurant
```

Then open <http://localhost:8000/preview.html>. If `raw.config.json`, `config.json`, or `image.png` changes, run `calibrate` and regenerate `preview.html` so the calibrated coordinates and QR payload are refreshed.

### 8) Generate the image

Use the saved image prompt to generate `image.png` through the configured Laravel AI image provider:

```bash
./weaver imagegen cafe-restaurant
```

You can override the provider, model, or output path:

```bash
./weaver imagegen cafe-restaurant \
  --provider=openai \
  --model=gpt-image-1 \
  --output=/path/to/generated-image.png
```

### 9) Export the generated image

```bash
./weaver export cafe-restaurant \
  --image=/path/to/generated-image.png \
  --output-dir=dist/cafe-restaurant
```

If `--image` is omitted, the command uses `.weaver/cafe-restaurant/image.png`. The command uses the final `.weaver/cafe-restaurant/config.json`; run `calibrate` first if it does not exist.

The export command validates that the manifest, design brief, and config exist, the image is a PNG, and its aspect ratio matches `canvas.aspect_ratio`. The resulting directory contains:

Typography in new configs is physical: use `placeholders.ssid.font_size_pt` and `placeholders.password.font_size_pt` (6–96 pt), with `canvas.width_mm`, `canvas.height_mm`, and `canvas.dpi` describing the print canvas. HTML previews use CSS points; PNG previews convert points with `pixels = points * dpi / 72`. Existing configs using `font_size_px` remain supported as a legacy fallback and do not require migration, though converting them to points is recommended when the physical format is known.

```text
dist/cafe-restaurant/
├── config.json
├── image.png
└── preview.png
```

If the fixture has a `preview.png`, it is copied as a thumbnail alongside the exported image.

To export every fixture under `.weaver`, use `export-all`. Each fixture's own
`image.png` is used and the output is written to `dist/<code>`:

```bash
./weaver export-all
```

Use `--fixtures-root` and `--output-dir` to change the input and output roots:

```bash
./weaver export-all --fixtures-root=.weaver --output-dir=dist
```

The exported `config.json` contains a `metadata` object with flattened manifest and design-brief fields. When the source config includes localized style metadata, it also includes an optional nested `metadata.style` object. Display labels for enum-backed values such as `style.print_target` should be resolved by the consuming service through the corresponding enum and translation files.

```json
{
  "schema_version": 1,
  "metadata": {
    "code": "cafe-restaurant",
    "category": "Cafe/Restaurant",
    "format": "A4/A5 Poster",
    "color_mode": "Mono",
    "name": "...",
    "description": "...",
    "color_direction": "...",
    "font_mood": "...",
    "style": {
      "theme": "...",
      "background": "..."
    }
  }
}
```

To validate an exported or generated config file independently, run:

```bash
./weaver config:validate path/to/config.json
```

#### Export config contract

The machine-readable contract for exported `config.json` files is available at
[`schemas/config.schema.json`](schemas/config.schema.json). It defines the required
top-level fields, metadata fields, layout structures, and value types.

The current contract uses `schema_version: 1`. Consumers should inspect this value
before deserializing the file. Incompatible structural changes increment the schema
version; optional fields may be added without changing the version.

Laravel applications can validate the JSON against this schema and then map it to a
consumer-owned DTO, such as a `spatie/laravel-data` class. The exported JSON remains
the integration boundary so consumers are not coupled to this package's PHP types.

These two files are intended to be imported by the Laravel service. The command does not delete existing files in the output directory, but it overwrites `config.json` and `image.png`.

### 9) Run the automated text pipeline

If you have an OpenRouter API key, you can run the automated brief/config/image-prompt pipeline. The default provider is `openrouter` with the `google/gemma-4-26b-a4b-it:free` model. You can change both defaults in `.env`:

The standalone `weaver` command automatically loads `.env` from the project root. Copy `.env.example` to `.env` and add your key once:

```bash
cp .env.example .env
# edit .env and set OPENROUTER_API_KEY
# PROMPT_WEAVER_PROVIDER=openrouter
# PROMPT_WEAVER_MODEL=@preset/openrouter-free-presets
./weaver pipe cafe-restaurant
```

The `--provider` and `--model` options override the corresponding `.env` values for a single run. OpenRouter model IDs and presets are passed through unchanged.

The `.env` file is ignored by Git. Existing shell environment variables take precedence over values in `.env`. Laravel applications can continue using Laravel's own `.env` loading; the package does not load `.env` from its service provider.

While `pipe` is running, it displays progress for the design brief, config JSON, and image prompt stages. Use `--no-progress` when running it from a script or when you only want the completion message:

```bash
./weaver pipe cafe-restaurant --no-progress
```

```bash
./weaver pipe cafe-restaurant --api-key=sk-or-v1-...
```

Or with explicit options:

```bash
./weaver pipe \
  --category="Cafe/Restaurant" \
  --format="A4/A5 Poster" \
  --color-mode=Mono \
  --provider=openrouter \
  --api-key=sk-or-v1-... \
  --model=google/gemma-4-26b-a4b-it:free \
  --color="warm brown and cream"
```

To use OpenAI instead, pass `--provider=openai` and an OpenAI API key:

```bash
./weaver pipe cafe-restaurant --provider=openai --api-key=sk-... --model=gpt-4o-mini
```

By default, the command displays progress and saves the generated files into the fixture directory without printing the full prompts or JSON responses. Use `--show-output` to print them as well:

```bash
./weaver pipe cafe-restaurant --show-output
```

The generated files are:

- `brief.prompt`
- `design-brief.json`
- `config.prompt`
- `raw.config.json`
- `image.prompt`

This command:

1. Generates the design-brief prompt and sends it to the configured Laravel AI provider
2. Receives the design-brief response as structured output
3. Generates the config prompt and sends it to the model
4. Parses the config JSON response
5. Generates the final image prompt
6. Saves the generated prompts and intermediate JSON responses to the fixture directory

### 9) Compare against fixtures

The repo already includes one complete example:

- `tests/Fixtures/cafe-restaurant/manifest.json`
- `tests/Fixtures/cafe-restaurant/design-brief.json`
- `tests/Fixtures/cafe-restaurant/raw.config.json`
- `tests/Fixtures/cafe-restaurant/config.json`
- `tests/Fixtures/cafe-restaurant/image.prompt`

The integration test reads those files and checks that:

1. The design-brief prompt is generated correctly.
2. The config prompt includes the generated brief.
3. The image prompt matches the saved `image.prompt` fixture.
4. The preview image can be generated from the fixture background without errors, with credential text and the QR code rendered in the calibrated area.

### 9.5) E2E test output

The E2E test writes files generated from real OpenRouter API calls to the ignored `.weaver` working directory:

- `.weaver/google-gemma-4-26b-a4b-it-free/`

These fixtures are generated automatically when you run the E2E test:

```bash
composer test:e2e
```

The E2E test uses the OpenRouter API with the `google/gemma-4-26b-a4b-it:free` model and saves all prompts and responses to the working directory for inspection and debugging.

### 10) Run the tests

```bash
composer test
```

If you change prompt wording, update the checked-in reference fixtures first, then run the tests again.

## Development

```bash
composer install
composer test
composer lint
```

## License

MIT
