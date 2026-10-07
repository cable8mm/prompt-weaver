# Purpose

- Prompt Weaver MUST let a user create Wi-Fi signage templates from design images and align variable-content placeholders with command-line tools.

# Problem

- AI-generated Wi-Fi signage does not reliably place QR codes, SSIDs, and passwords, so the user needs a template workflow that calibrates placeholder coordinates and aligns variable content during later processing.

# Product Goals

- Prompt Weaver MUST enable a user to prepare a template through the Generate, Preview, Human Approval, and Publish stages.
- Prompt Weaver MUST enable a user to calibrate the coordinates of placeholders for QR codes, SSIDs, and passwords.
- Prompt Weaver MUST enable a user to upload reviewed templates to WifiNote as a template pack.

# Users

- The user provides design inputs, prepares templates, reviews previews, and decides whether to publish.
- A consuming service generates the actual QR code, SSID, and password values for template placeholders.

# Inputs

- The user MUST provide a template code to the template preparation commands.
- `init` MUST accept the template code as a positional argument.
- `init` MUST accept `--category`, `--format`, `--color-mode`, `--layout`, and `--fixtures-root`.
- `init` MUST default `--category` to `Cafe/Restaurant` when omitted.
- `init` MUST default `--format` to `A4/A5 Poster` when omitted.
- `init` MUST default `--color-mode` to `Mono` when omitted.
- `init` MUST default `--layout` to `centered` when omitted.
- Template preparation commands MUST default `--fixtures-root` to `.weaver` when omitted.
- `pipe` MUST accept an optional template code as a positional argument.
- `pipe` MUST accept `--category`, `--format`, `--color-mode`, `--layout`, `--provider`, `--api-key`, `--model`, `--color`, `--fixtures-root`, `--no-progress`, and `--show-output`.
- `calibrate` MUST accept an optional template code as a positional argument and the `--fixture` and `--fixtures-root` options.
- `preview` MUST accept an optional template code as a positional argument and the `--fixture`, `--code`, `--output`, and `--fixtures-root` options.
- `preview` MUST default `--output` to `preview.png` in the selected working template when omitted.
- `code` MUST accept a required template code as a positional argument and the `--fixtures-root` and `--dist-root` options.
- `code` MUST default `--dist-root` to `dist` when omitted.
- `export` MUST accept a required template code as a positional argument and the `--image`, `--output-dir`, and `--fixtures-root` options.
- `export` MUST default `--image` to the working template's `image.png` when omitted.
- `export` MUST default `--output-dir` to `dist/<template-code>` when omitted.
- `publish` MUST accept the `--dist-root` option.
- `publish` MUST default `--dist-root` to `dist` when omitted.
- `init` MUST accept `Cafe/Restaurant`, `Office/Coworking`, `Stay/Hotel`, `Event/Exhibition`, and `Other` as category values.
- `init` and `pipe` MUST accept `A4/A5 Poster`, `A6/A7 Poster`, and `Mini Square` as format values.
- `init` and `pipe` MUST accept `Color` and `Mono` as color-mode values.
- `init` and `pipe` MUST accept `centered`, `editorial`, `qr-focus`, and `mini-square` as layout values.
- `pipe` MUST produce an image prompt for an external image-generation service.
- The user MUST supply the generated design image to the working template before calibration.
- The template MUST contain placeholders for QR code, SSID, and password content.
- The user MUST provide a WifiNote server URL and Personal Access Token to `login`.
- The consuming service MUST provide the actual QR code, SSID, and password values when filling the template placeholders.
- The user MUST provide confirmation to `publish` before an upload proceeds.

# Outputs

- `init` MUST produce `manifest.json` with `code`, `category`, `format`, `color_mode`, and `layout` values.
- `pipe` MUST produce `brief.prompt`, `design-brief.json`, `config.prompt`, `raw.config.json`, and `image.prompt` in the working template when a template code is supplied.
- `calibrate` MUST update the working template `config.json` with calibrated placeholder coordinates.
- The Preview workflow MUST produce a preview of a template.
- Export MUST produce a template directory under `dist/`.
- Export MUST produce `config.json`, `image.png`, and `image.prompt` in the exported template directory.
- Export MUST include `preview.png` in the exported template directory when a preview image exists.
- Each exported template directory MUST contain `image.png` and `manifest.json`.
- The exported `manifest.json` MUST contain `code`, `category`, `format`, `color_mode`, and `layout`.
- `login` MUST persist the WifiNote server URL and Personal Access Token across command invocations.
- `publish` MUST create one ZIP template pack containing every template directory under the selected publication root, represented under `templates/<code>/`.
- The ZIP template pack MUST contain a root-level `manifest.json` whose only key is `templates`, a list of the packaged codes.
- Each packaged template MUST contain exactly `config.json`, `image.png`, and `preview.png` under `templates/<code>/`.
- A Template Pack MUST contain no more than 50 templates and MUST be no larger than 100 MiB; otherwise `publish` MUST fail before requesting confirmation or uploading.
- The ZIP template pack MUST use a filename containing a timestamp in the form `template-pack-YYYYMMDD-HHMMSS.zip`.
- `preview` MUST write an HTML preview when the selected output path has an `.html` extension.
- A successful upload MUST report that WifiNote accepted the Template Pack for import.

# Constraints

- None stated.

# External Systems

### [UNVERIFIED] AI text service

- `pipe` MUST rely on an AI text service to produce a design brief, configuration data, and an image prompt.

### [UNVERIFIED] Image-generation service

- The user MUST be able to provide `image.prompt` to an external image-generation service.
- The user MUST be able to supply the resulting design image to the working template.

### [UNVERIFIED] Consuming service

- The consuming service MUST use the template placeholders to position actual QR code, SSID, and password values.

### [DOCUMENTED; LIVE VERIFIED FOR 202] WifiNote server

- The WifiNote server MUST accept a template pack through `POST /api/template-packs/upload`.
- The upload MUST use `multipart/form-data`.
- The upload MUST include an Authorization header.
- The Authorization header MUST use the `Bearer` scheme.
- The ZIP root manifest MUST contain only a `templates` list of 1 to 50 valid template codes. Each code directory MUST contain `config.json`, `image.png`, and `preview.png`, with `metadata.code` matching the manifest code.
- The upload file MUST be a ZIP no larger than 100 MiB.
- A successful upload responds with `202 Accepted` and stores the ZIP in a private inbox.
- Upload acceptance and Template import are separate operations. Import is performed by `template-packs:import` or the Laravel Scheduler; upload alone MUST NOT be described as automatic import.
- One live single-template probe verified `202 Accepted`, an upload identifier, and the ZIP in the private inbox. The server's other response codes are not claimed as live behavior unless separately observed; client-side mappings are verified by local HTTP tests.

# User Interface

### SCR-001

- Purpose: Create a working template from a template code.
- Elements:
  - EL-001: Template code input; the user MUST provide a template code to `./weaver init`.
  - EL-014: Category input; the user MUST be able to select `Cafe/Restaurant`, `Office/Coworking`, `Stay/Hotel`, `Event/Exhibition`, or `Other` with `--category`.
  - EL-015: Format input; the user MUST be able to select `A4/A5 Poster`, `A6/A7 Poster`, or `Mini Square` with `--format`.
  - EL-016: Color-mode input; the user MUST be able to select `Color` or `Mono` with `--color-mode`.
  - EL-017: Layout input; the user MUST be able to select `centered`, `editorial`, `qr-focus`, or `mini-square` with `--layout`.
  - EL-018: Working template root input; the user MUST be able to select a root path with `--fixtures-root`.
- States:
  - Initial: No working template has been created for the supplied code.
  - Populated: The command reports the created template manifest.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Successful `init` -> SCR-002.
- Interactions:
  - `./weaver init <template-code> [--category=...] [--format=...] [--color-mode=...] [--layout=...] [--fixtures-root=...]` -> creates the working template and its `manifest.json`.

### SCR-002

- Purpose: Generate design prompts and configuration data for a working template.
- Elements:
  - EL-002: Template code input; the user MUST provide the code to `./weaver pipe`.
  - EL-019: Category and format inputs; when no template code is supplied, the user MUST provide `--category` and `--format`.
  - EL-020: Optional generation inputs; the user MUST be able to set `--color-mode`, `--layout`, `--color`, `--provider`, `--api-key`, and `--model`.
  - EL-021: Working template root input; the user MUST be able to select a root path with `--fixtures-root`.
  - EL-022: Progress control; the user MUST be able to suppress progress output with `--no-progress`.
  - EL-023: Generated data output control; the user MUST be able to print prompts and JSON responses with `--show-output`.
- States:
  - Initial: The working template is available for generation.
  - Loading: The command displays pipeline progress unless `--no-progress` is supplied.
  - Populated: With a template code, the command saves the five named output files to the working template and reports the destination.
  - Populated: Without a template code, the command prints prompts and JSON responses only when `--show-output` is supplied.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Successful `pipe` -> SCR-003.
- Interactions:
  - `./weaver pipe [<template-code>] [--category=...] [--format=...] [--color-mode=...] [--layout=...] [--provider=...] [--api-key=...] [--model=...] [--color=...] [--fixtures-root=...] [--no-progress] [--show-output]` -> runs text-prompt generation and saves generated files when a template code is supplied.
  - User provides `image.prompt` to an external image-generation service -> receives a generated design image.

### SCR-003

- Purpose: Align placeholder coordinates to the generated design image.
- Elements:
  - EL-003: Template code input; the user MUST provide the code to `./weaver calibrate`.
  - EL-004: Placeholder coordinate data; the user MUST be able to have the command calibrate positions for QR code, SSID, and password placeholders.
  - EL-024: Design image input; the user MUST supply the generated design image to the working template.
  - EL-025: Working template location; the user MUST be able to select a template directory with `--fixture` or a working root with `--fixtures-root`.
- States:
  - Initial: The generated design image and template are available for calibration.
  - Populated: Calibrated placeholder coordinates are available in the template.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Successful `calibrate` -> SCR-004.
- Interactions:
  - `./weaver calibrate [<template-code>] [--fixture=<template-directory>] [--fixtures-root=...]` -> calibrates placeholder coordinates and updates `config.json`.

### SCR-004

- Purpose: Render a template preview for human review.
- Elements:
  - EL-005: Template code input; the user MUST provide the code to `./weaver preview`.
  - EL-026: Template location input; the user MUST be able to select a template directory with `--fixture`, a template code with `--code`, or a working root with `--fixtures-root`.
  - EL-027: Preview output input; the user MUST be able to select an output path with `--output`.
- States:
  - Initial: A calibrated template is available for preview.
  - Populated: A rendered preview is available for review.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Preview is ready -> SCR-006.
- Interactions:
  - `./weaver preview [<template-code>] [--fixture=<template-directory>] [--code=<template-code>] [--output=<path>] [--fixtures-root=...]` -> renders a preview to the selected path or to `preview.png` in the working template by default; an HTML output path produces an HTML preview.

### SCR-005

- Purpose: Let the user visually review every template before publication.
- Elements:
  - EL-006: Template preview; the user MUST be able to inspect the rendered template.
  - EL-007: Human review decision; the user decides whether the template is ready to publish.
- States:
  - Initial: A template preview is available for review.
  - Populated: The user has inspected the preview and decided whether the template is ready.
- Navigation:
  - User completes review -> SCR-009.
- Interactions:
  - User inspects a preview -> the user determines whether its placeholder positions are acceptable.
  - User completes the review -> no approval record is created by the CLI.

### SCR-006

- Purpose: Derive and apply the final template code after preview review.
- Elements:
  - EL-008: Template code input; the user MUST provide the code to `./weaver code`.
  - EL-028: Working template root input; the user MUST be able to select a root path with `--fixtures-root`.
  - EL-029: Export root input; the user MUST be able to select an export root with `--dist-root`.
- States:
  - Initial: A template preview exists.
  - Populated: The template code derived from the configured style theme is applied to the working template and any matching exported template.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Successful `code` -> SCR-007.
- Interactions:
  - `./weaver code <template-code> [--fixtures-root=...] [--dist-root=...]` -> derives a code from the configured style theme, renames the template when the code changes, and updates the displayed result.

### SCR-007

- Purpose: Export a prepared template for publication.
- Elements:
  - EL-009: Template code input; the user MUST provide the code to `./weaver export`.
  - EL-030: Design image input; the user MUST be able to select an image path with `--image`.
  - EL-031: Export directory input; the user MUST be able to select an export directory with `--output-dir`.
  - EL-032: Working template root input; the user MUST be able to select a root path with `--fixtures-root`.
- States:
  - Initial: The derived template code is applied and the template is ready to export.
  - Populated: The exported template directory is available under `dist/`.
  - Error: The command reports failure and exits with a non-zero status.
- Navigation:
  - Successful `export` -> SCR-005 for review of the exported template.
- Interactions:
  - `./weaver export <template-code> [--image=<path>] [--output-dir=<path>] [--fixtures-root=...]` -> exports the template image and metadata to `dist/<template-code>/` by default.

### SCR-008

- Purpose: Configure the WifiNote server credentials used for publication.
- Elements:
  - EL-010: WifiNote server URL input; the user MUST be able to enter a server URL.
  - EL-011: Personal Access Token input; the user MUST be able to enter a token without its characters being displayed.
- States:
  - Initial: The command requests the server URL.
  - Populated: The command has accepted the server URL and requests the token.
  - Error: Invalid or missing credentials produce an error and a non-zero exit status.
- Navigation:
  - Credentials saved -> SCR-009.
- Interactions:
  - `./weaver login` -> requests the WifiNote server URL and Personal Access Token.
  - User enters valid credentials -> saves them for subsequent command invocations.

### SCR-009

- Purpose: Upload a template pack to WifiNote.
- Elements:
  - EL-012: Template pack; the user MUST be able to initiate publication of the reviewed templates under `dist/`.
  - EL-013: Publication confirmation; the user MUST be able to approve or decline the upload, with decline as the default.
  - EL-033: Template root input; the user MUST be able to select a template root with `--dist-root`.
- States:
  - Initial: The command creates a template pack from every template directory under `dist/`.
  - Populated: The command is waiting for user confirmation.
  - Error: An upload failure is reported and exits with a non-zero status.
- Navigation:
  - User approves -> WifiNote upload.
  - User declines or accepts the default -> command ends without uploading.
- Interactions:
  - `./weaver publish [--dist-root=...]` -> creates a template pack and requests confirmation.
  - User approves -> uploads the template pack to WifiNote.
  - User declines -> does not upload the template pack.

# Functional Requirements

- FR-001: `init` MUST create a working template and write `manifest.json` containing `code`, `category`, `format`, `color_mode`, and `layout`.
- FR-002: `pipe` MUST write `brief.prompt`, `design-brief.json`, `config.prompt`, `raw.config.json`, and `image.prompt` when a template code is supplied.
- FR-003: `calibrate` MUST determine placeholder coordinates against the user-supplied design image.
- FR-004: `calibrate` MUST write calibrated coordinates for the QR code, SSID, and password placeholders to `config.json`.
- FR-005: `preview` MUST render a template preview for human inspection.
- FR-006: The user MUST review every template under `dist/` before starting publication.
- FR-007: The CLI MUST NOT record or enforce Human Approval.
- FR-008: `code` MUST derive a template code from the configured style theme.
- FR-009: `code` MUST update the working template and any matching exported template when the derived code differs from the supplied code.
- FR-010: `login` MUST request a WifiNote server URL.
- FR-011: `login` MUST request a Personal Access Token without displaying its characters.
- FR-012: `login` MUST persist the server URL and token at `~/.config/prompt-weaver/config.json`.
- FR-013: `publish` MUST include every template directory directly under the selected publication root under `templates/<code>/` in one ZIP template pack. It MUST reject packs with more than 50 templates or a ZIP larger than 100 MiB before requesting confirmation.
- FR-014: The ZIP template pack MUST contain a root-level `manifest.json`.
- FR-015: The root-level `manifest.json` MUST contain only a `templates` list of the packaged template codes.
- FR-016: `publish` MUST request user confirmation before sending an upload request.
- FR-017: The confirmation request MUST default to declining publication.
- FR-018: `publish` MUST NOT upload the template pack when the user declines publication.
- FR-019: `publish` MUST upload the ZIP template pack to `POST /api/template-packs/upload` using `multipart/form-data`.
- FR-020: `publish` MUST include the configured Personal Access Token in an Authorization header.
- FR-021: `publish` MUST report that WifiNote accepted the Template Pack for import after a successful response; it MUST NOT claim that import has already completed.
- FR-022: Successful commands and declined publication MUST exit with status 0.
- FR-023: Command errors MUST exit with a non-zero status.
- FR-024: `export` MUST export the supplied template to `dist/<template-code>/`.
- FR-025: `export` MUST include `config.json`, `image.png`, and `image.prompt` in the exported template directory.
- FR-026: `export` MUST include `preview.png` when a preview image exists.
- FR-027: `init` MUST accept `--category`, `--format`, `--color-mode`, `--layout`, and `--fixtures-root`.
- FR-028: `pipe` MUST accept `--category`, `--format`, `--color-mode`, `--layout`, `--provider`, `--api-key`, `--model`, `--color`, `--fixtures-root`, `--no-progress`, and `--show-output`.
- FR-029: `calibrate` MUST accept `--fixture` and `--fixtures-root`.
- FR-030: `preview` MUST accept `--fixture`, `--code`, `--output`, and `--fixtures-root`.
- FR-031: `code` MUST accept `--fixtures-root` and `--dist-root`.
- FR-032: `export` MUST accept `--image`, `--output-dir`, and `--fixtures-root`.
- FR-033: `publish` MUST accept `--dist-root`.
- FR-034: The exported `manifest.json` MUST contain `code`, `category`, `format`, `color_mode`, and `layout`.
- FR-035: The Authorization header MUST use the `Bearer` scheme.

# User Flows

- UF-001: The user runs `./weaver init <template-code>` -> the user runs `./weaver pipe <template-code>` -> the user provides `image.prompt` to an external image-generation service -> the user supplies the generated image to the working template -> the user runs `./weaver calibrate <template-code>` -> the user runs `./weaver preview <template-code>` -> the user runs `./weaver code <template-code>` -> the user runs `./weaver export <derived-template-code>` -> the user reviews every template under `dist/`.
- UF-002: The user runs `./weaver login` -> the user enters the WifiNote server URL -> the user enters the hidden Personal Access Token -> the credentials are saved.
- UF-003: The user reviews every template under `dist/` -> the user runs `./weaver publish` -> the user approves or declines -> approval uploads one template pack, and decline ends without upload.

# Error Conditions

- ERR-001: WifiNote responds with HTTP 401 -> the CLI reports that the Personal Access Token was rejected.
- ERR-002: WifiNote responds with HTTP 403 -> the CLI reports that the token lacks upload permission.
- ERR-003: WifiNote responds with HTTP 422 -> the CLI reports that the template pack was not accepted.
- ERR-004: WifiNote responds with HTTP 500 -> the CLI reports a WifiNote server error.
- ERR-005: The upload request times out -> the CLI reports a timeout.
- ERR-006: A network error prevents the upload request -> the CLI reports a network error.
- ERR-007: Any error condition MUST exit with a non-zero status.

# Non-Goals

- Prompt Weaver MUST NOT modify the WifiNote database.
- Prompt Weaver MUST NOT implement WifiNote import logic.
- Prompt Weaver MUST NOT implement a general-purpose publishing system.
- Prompt Weaver MUST NOT publish through SSH.
- Prompt Weaver MUST NOT publish through rsync.
- Prompt Weaver MUST NOT publish through GitHub.

# Acceptance Criteria

- AC-001: Given a template code, when the user runs `./weaver init <template-code>`, then SCR-001 creates a working template and `manifest.json` with all five required fields, satisfying FR-001.
- AC-002: Given a working template, when the user runs `./weaver pipe <template-code>`, then SCR-002 saves the five named generation files, satisfying FR-002.
- AC-003: Given a generated design image supplied to the working template, when the user runs `./weaver calibrate <template-code>`, then SCR-003 writes the coordinates required by FR-004 to `config.json`, satisfying FR-003 and FR-004.
- AC-004: Given a calibrated template, when the user runs `./weaver preview <template-code>`, then SCR-004 renders a preview and FR-005 passes.
- AC-005: Given templates under `dist/`, when the user reviews each template before publication, then SCR-005 provides no CLI approval record and the CLI does not enforce approval, satisfying FR-006 and FR-007.
- AC-006: Given a reviewed template, when the user runs `./weaver code <template-code>`, then SCR-006 applies the code derived from the configured style theme, satisfying FR-008 and FR-009.
- AC-007: Given a derived template code, when the user runs `./weaver export <derived-template-code>`, then SCR-007 creates the exported template directory with the files required by FR-025, FR-026, and FR-034, satisfying FR-024, FR-025, FR-026, and FR-034.
- AC-008: When the user runs `./weaver login`, then SCR-008 requests a server URL and a hidden Personal Access Token and saves both at the specified path, satisfying FR-010, FR-011, and FR-012.
- AC-009: Given one or more importable template directories under the selected root within WifiNote limits, when `publish` creates a template pack at SCR-009, then the ZIP contains each directory's required three files under `templates/<code>/` and a root manifest containing only the complete `templates` list, satisfying FR-013, FR-014, and FR-015.
- AC-010: Given a template pack and configured credentials, when the user runs `./weaver publish`, then SCR-009 requests confirmation before upload and defaults to decline, satisfying FR-016 and FR-017.
- AC-011: Given a pending publication confirmation at SCR-009, when the user declines or accepts the default, then no upload request is sent and the command exits 0, satisfying FR-018 and FR-022.
- AC-012: Given a pending publication confirmation, when the user approves, then SCR-009 sends the ZIP to WifiNote using multipart upload and the Authorization header required by FR-020 and FR-035, satisfying FR-019, FR-020, and FR-035.
- AC-013: Given a successful WifiNote response at SCR-009, when the upload completes, then the CLI reports upload acceptance for import and exits 0, satisfying FR-021 and FR-022.
- AC-014: Given ERR-001, ERR-002, ERR-003, ERR-004, ERR-005, or ERR-006, when the user runs `./weaver publish` at SCR-009, then the CLI reports the corresponding error and exits non-zero, satisfying FR-022 and FR-023.
