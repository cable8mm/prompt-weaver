# Purpose

- Define the internal responsibilities, information flow, and invariants for the template preparation and WifiNote publication workflow.
- Keep externally owned work outside Prompt Weaver.

# Core Concepts

- Working Template: A template being prepared before export. It contains a template manifest and may contain generated prompts, configuration, a design image, calibrated placeholder coordinates, and a preview.
- Exported Template: A prepared template made available under the publication root. It contains the template manifest, configuration, design image, image prompt, and a preview when one exists.
- Template Manifest: Metadata that identifies a template code, category, format, color mode, and layout.
- Template Pack: One WifiNote-compatible archive containing each template's `config.json`, `image.png`, and `preview.png` under `templates/<code>/`, plus a pack manifest at the archive root.
- Pack Manifest: The root `manifest.json` whose only key is `templates`, a list of packaged template codes.
- Placeholder Coordinates: Positions recorded for QR code, SSID, and password content. They describe placement; they are not the actual values.
- Human Review: The user's manual inspection of every Exported Template before publication. Prompt Weaver neither records nor enforces this decision.

# System Flow

- The user initializes a Working Template by providing its code and optional category, format, color-mode, layout, and working-root inputs.
- The user runs prompt generation with the Working Template or with category and format inputs.
- Prompt generation obtains structured design and configuration results from the AI text boundary and produces an image prompt.
- When a Working Template is selected, Prompt Weaver saves the generated prompt and configuration artifacts to it.
- The user submits the image prompt to an external image-generation service and supplies the resulting design image to the Working Template.
- The user calibrates the Working Template against the supplied design image.
- The user renders and inspects a preview.
- The user derives the final template code and exports the prepared template.
- The user reviews every Exported Template under the publication root.
- The user configures WifiNote credentials independently of template preparation.
- The user starts publication. Prompt Weaver checks the configured credentials, creates one Template Pack, and requests confirmation.
- A declined confirmation ends publication without an upload.
- An approved confirmation sends the Template Pack to WifiNote.
- WifiNote owns importing the uploaded Template Pack.
- A consuming service uses Placeholder Coordinates and supplies the actual QR code, SSID, and password values. This work is outside Prompt Weaver.

# Components

## Command Interface

- Responsibilities:
  - Accept command names, arguments, and options for template preparation, login, and publication.
  - Request interactive values and publication confirmation.
  - Present progress, generated output, success, or failure to the user.
  - Return success for successful commands and declined publication.
  - Return a non-zero status for command failures.
- Inputs:
  - User-provided command arguments and options.
  - Interactive server URL, Personal Access Token, and publication decision.
  - Results and failures from internal components.
- Outputs:
  - Calls to the relevant internal component.
  - User-visible command output and an exit status.
- Ownership boundaries:
  - Owns command interaction and command-result presentation.
  - Does not own template transformations, credential persistence, archive contents, or remote import behavior.

## Template Workspace

- Responsibilities:
  - Create a Working Template and its Template Manifest.
  - Resolve a template reference to a Working Template.
  - Provide template metadata and artifacts to preparation components.
  - Save generation outputs and calibrated configuration updates to the selected Working Template.
- Inputs:
  - Template code, category, format, color mode, layout, and working-root selection.
  - Generated prompt and configuration results.
  - Updated Placeholder Coordinates.
- Outputs:
  - A Working Template with a Template Manifest.
  - Persisted generation artifacts and calibrated configuration.
  - Resolved template metadata and artifact inputs.
- Ownership boundaries:
  - Owns Working Template metadata and preparation artifacts.
  - Does not own the externally generated design image or exported-template contents.

## Prompt Generation

- Responsibilities:
  - Produce a design brief prompt and obtain its structured response.
  - Produce a configuration prompt using the design brief and obtain its structured response.
  - Produce an image prompt from the configuration result.
  - Return generated prompts and structured results to the calling workflow.
- Inputs:
  - Category, format, color mode, layout, optional color direction, and AI service selection inputs.
  - Structured responses from the AI text boundary.
- Outputs:
  - Design brief prompt and structured design brief.
  - Configuration prompt and structured configuration.
  - Image prompt.
- Ownership boundaries:
  - Owns the prompt-generation sequence and its returned results.
  - Does not generate the design image or persist results to a Working Template.
  - Does not own the behavior or availability of the AI text boundary.

## AI Text Boundary [UNVERIFIED]

- Responsibilities:
  - Accept requests to produce a structured design brief and structured configuration.
  - Return generated structured responses to Prompt Generation.
- Inputs:
  - Prompts and selected service options supplied by Prompt Generation.
- Outputs:
  - Structured responses consumed by Prompt Generation, or a failure returned to the calling command.
- Ownership boundaries:
  - The service owns response generation and availability.
  - Prompt Weaver owns request construction, response consumption, and command failure reporting.
  - The accepted request and response behavior has not been observed and is not further specified here.

## Image Handoff

- Responsibilities:
  - Receive the image prompt from the user.
  - Make the user-generated design image available to the selected Working Template.
- Inputs:
  - Image prompt.
  - Design image generated by the user outside Prompt Weaver.
- Outputs:
  - A user-supplied design image available to the Working Template.
- Ownership boundaries:
  - The user owns image generation and supplying the resulting image.
  - Prompt Weaver does not own or invoke the image-generation service.

## Calibration

- Responsibilities:
  - Analyze the supplied design image and determine Placeholder Coordinates for QR code, SSID, and password.
  - Return the calibrated configuration to the Template Workspace.
- Inputs:
  - Design image.
  - Working Template configuration.
- Outputs:
  - Updated configuration containing Placeholder Coordinates.
  - A calibration failure returned to the calling command when calibration cannot complete.
- Ownership boundaries:
  - Owns coordinate calibration.
  - Does not own design-image generation, actual Wi-Fi values, or later rendering by a consuming service.

## Preview Renderer

- Responsibilities:
  - Render a preview from the selected template image and configuration.
  - Produce an HTML preview when the selected output path has an `.html` extension.
  - Produce a PNG preview for other output paths.
- Inputs:
  - Selected Working Template or template reference.
  - Design image, configuration, render options, and output path.
- Outputs:
  - A rendered preview at the selected or default output path.
  - A render failure returned to the calling command.
- Ownership boundaries:
  - Owns preview rendering and output format selection.
  - Does not change the source design image or configuration.

## Template Code Manager

- Responsibilities:
  - Derive a template code from the configured style theme.
  - Apply a changed code to the Working Template and any matching Exported Template.
- Inputs:
  - Template reference, configured style theme, working-root selection, and publication-root selection.
- Outputs:
  - The derived template code.
  - Updated template identity wherever the matching template exists.
  - A code-application failure returned to the calling command.
- Ownership boundaries:
  - Owns derivation and synchronization of the template code.
  - Does not own the theme configuration or template visual design.

## Template Exporter

- Responsibilities:
  - Validate the source artifacts required for export.
  - Produce an Exported Template under the selected publication root.
  - Include the Template Manifest, configuration, design image, and image prompt.
  - Include the preview when a preview image exists.
- Inputs:
  - Working Template, design image selection, and export destination.
- Outputs:
  - An Exported Template containing the artifacts required by the product contract.
  - An export failure returned to the calling command.
- Ownership boundaries:
  - Owns the exported artifact set and its destination.
  - Does not own upstream generation or the user's Human Review.

## Credential Manager

- Responsibilities:
  - Accept and validate the WifiNote server URL and Personal Access Token.
  - Persist credentials for subsequent command invocations.
  - Supply configured credentials to the publication workflow.
- Inputs:
  - Server URL and Personal Access Token from the user.
- Outputs:
  - Persisted credentials or a credential validation/persistence failure.
  - Configured server URL and token for an upload attempt.
- Ownership boundaries:
  - Owns WifiNote credential persistence and retrieval.
  - Does not own the upload or the user's authorization with WifiNote.

## Template Packager

- Responsibilities:
  - Select every template directory directly under the selected publication root.
  - Create one Template Pack containing those directories.
  - Add a Pack Manifest at the archive root.
  - Name the archive with the required timestamp form.
- Inputs:
  - Publication root and Prompt Weaver generator identity, version, and creation time.
- Outputs:
  - A Template Pack ready for confirmation and upload.
  - A packaging failure returned to the publication workflow.
- Ownership boundaries:
  - Owns archive composition and temporary archive lifecycle.
  - Does not own template preparation, confirmation, credentials, or remote import.

## Publication Workflow

- Responsibilities:
  - Obtain configured credentials before publication.
  - Request user confirmation before sending an upload request.
  - Skip upload when the user declines.
  - Submit the approved Template Pack to the WifiNote Upload Boundary.
  - Report successful upload acceptance for import after a successful response.
- Inputs:
  - Publication root, configured credentials, Template Packager result, and user confirmation.
- Outputs:
  - Upload request or a declined-publication result.
  - Publication success or a categorized failure for the Command Interface.
- Ownership boundaries:
  - Owns the order and outcome of the publication workflow.
  - Does not create WifiNote import behavior or modify WifiNote data.

## WifiNote Upload Boundary [DOCUMENTED; LIVE VERIFIED FOR 202]

- Responsibilities:
  - Send an approved Template Pack to `POST /api/template-packs/upload`.
  - Use multipart form data and the configured Bearer token.
  - Classify the specified authentication, permission, rejection, server, timeout, and network failures.
- Inputs:
  - WifiNote server URL, Personal Access Token, and Template Pack.
- Outputs:
  - A successful upload result or a categorized upload failure.
- Ownership boundaries:
  - Prompt Weaver owns the upload request and its local error reporting.
  - WifiNote owns request acceptance and a separate, explicit import process.
  - A live single-template request verified `202 Accepted`, an upload identifier, and private inbox storage without running import.
  - Local client tests verify response classification, including response codes not emitted by the current WifiNote upload route.

## Image-Generation Boundary [UNVERIFIED]

- Responsibilities:
  - Accept an image prompt from the user and return a generated design image to the user.
- Inputs:
  - Image prompt supplied by the user.
- Outputs:
  - Design image supplied by the user to the Working Template.
- Ownership boundaries:
  - The external service owns image generation.
  - Prompt Weaver does not invoke or control this service.
  - Service behavior has not been observed and is not further specified here.

## Consuming Service Boundary [UNVERIFIED]

- Responsibilities:
  - Use template placeholders and Placeholder Coordinates when filling a template.
  - Supply actual QR code, SSID, and password values.
- Inputs:
  - Exported template and actual values supplied by the consuming service.
- Outputs:
  - A filled template produced outside Prompt Weaver.
- Ownership boundaries:
  - The consuming service owns value generation and template filling.
  - Prompt Weaver owns only the template artifacts and Placeholder Coordinates.
  - Service behavior has not been observed and is not further specified here.

# Component Responsibilities

- Command-specific inputs and outputs are passed through the Command Interface to one preparation or publication component.
- Template Workspace owns editable preparation state; Template Exporter owns the exported copy.
- Prompt Generation owns generated prompts and structured AI results; Template Workspace owns their persistence.
- Calibration owns computed Placeholder Coordinates; Template Workspace owns their persistence.
- Preview Renderer owns preview output only and does not mutate template source data.
- Template Packager owns archive composition; Publication Workflow owns confirmation and upload sequencing.
- Credential Manager owns credentials; WifiNote Upload Boundary consumes them only for the approved request.
- External boundaries own their remote behavior; Prompt Weaver owns request initiation and reporting of received outcomes.

# Responsibility Boundaries

- Prompt Weaver owns template preparation, local artifact changes, credential handling, packaging, confirmation, and upload initiation.
- The user owns review of all Exported Templates and creation and handoff of the design image.
- The AI text service owns generated response content and service availability.
- The image-generation service owns the design image generation process.
- WifiNote owns accepting and importing the Template Pack.
- The consuming service owns actual Wi-Fi value generation and filling template placeholders.
- Human Review is not a persistent state and is not a publication gate enforced by Prompt Weaver.

# Data Flow

- Template code and selected manifest values -> Template Workspace -> Working Template and Template Manifest.
- Template Manifest values or explicit generation inputs -> Prompt Generation -> prompts and structured results.
- Prompts and structured results -> Template Workspace -> persisted generation artifacts when a Working Template is selected.
- Image prompt -> user -> Image-Generation Boundary -> user-supplied design image -> Working Template.
- Working Template image and configuration -> Calibration -> updated Placeholder Coordinates -> Template Workspace.
- Working Template image and configuration -> Preview Renderer -> preview artifact.
- Working Template style theme -> Template Code Manager -> synchronized template code.
- Working Template and selected image -> Template Exporter -> Exported Template.
- Exported Templates -> user Human Review -> publication root remains unchanged until publication starts.
- User server URL and token -> Credential Manager -> persisted credentials.
- Publication root -> Template Packager -> one Template Pack with a root Pack Manifest.
- Template Pack and configured credentials -> Publication Workflow -> user confirmation.
- Declined confirmation -> Publication Workflow -> no upload request.
- Approved confirmation -> WifiNote Upload Boundary -> WifiNote server -> upload result.
- Exported Template and actual Wi-Fi values -> Consuming Service Boundary -> filled template outside Prompt Weaver.

# Architectural Rules

- Each command invocation MUST enter through the Command Interface.
- Each component MUST accept only inputs within its ownership boundary.
- Each output artifact MUST have one owning component.
- The Template Manifest MUST remain part of each exported template.
- The Pack Manifest MUST be at the root of the Template Pack and MUST remain distinct from each Template Manifest.
- The Template Packager MUST include all template directories directly under the selected publication root in one archive.
- The Publication Workflow MUST obtain credentials and a Template Pack before requesting confirmation.
- The Publication Workflow MUST NOT send an upload request before explicit user approval.
- The Publication Workflow MUST NOT send an upload request after a declined confirmation.
- The WifiNote Upload Boundary MUST NOT own importing the Template Pack.
- Prompt Weaver MUST NOT generate or fill actual QR code, SSID, or password values.
- Human Review MUST remain user-owned; no component may create or enforce an approval record.
- Failure at a component boundary MUST return to the Command Interface as a command error unless the user declined publication.

# Failure Boundaries

- Invalid command input or an invalid template reference -> the responsible preparation component returns failure -> the Command Interface reports an error and the command exits non-zero.
- Missing, unreadable, or invalid template artifacts -> the owning preparation component returns failure -> the Command Interface reports an error and the command exits non-zero.
- AI text service failure or unusable structured response -> Prompt Generation returns failure -> the Command Interface reports an error and the command exits non-zero.
- Design image calibration failure -> Calibration returns failure -> the Command Interface reports an error and the command exits non-zero.
- Preview rendering failure -> Preview Renderer returns failure -> the Command Interface reports an error and the command exits non-zero.
- Template code update or export failure -> the owning component returns failure -> the Command Interface reports an error and the command exits non-zero.
- Missing, invalid, or unsavable credentials -> Credential Manager returns failure -> the Command Interface reports an error and the command exits non-zero.
- Missing publication root, no template directories, or archive creation failure -> Template Packager returns failure -> the Command Interface reports an error and the command exits non-zero.
- Declined publication confirmation -> Publication Workflow returns a declined result -> the Command Interface reports cancellation and the command exits zero without an upload request.
- WifiNote HTTP 401 -> WifiNote Upload Boundary returns an authentication failure -> the Command Interface reports token rejection and the command exits non-zero.
- WifiNote HTTP 403 -> WifiNote Upload Boundary returns a permission failure -> the Command Interface reports insufficient upload permission and the command exits non-zero.
- WifiNote HTTP 422 -> WifiNote Upload Boundary returns a pack-rejection failure -> the Command Interface reports that the Template Pack was not accepted and the command exits non-zero.
- WifiNote HTTP 500 -> WifiNote Upload Boundary returns a server failure -> the Command Interface reports a WifiNote server error and the command exits non-zero.
- Upload timeout -> WifiNote Upload Boundary returns a timeout failure -> the Command Interface reports a timeout and the command exits non-zero.
- Network error -> WifiNote Upload Boundary returns a network failure -> the Command Interface reports a network error and the command exits non-zero.
- Any other unsuccessful WifiNote response -> WifiNote Upload Boundary returns an upload failure -> the Command Interface reports an error and the command exits non-zero.

## [UNVERIFIED] External Service Failure Details

- External service response details beyond the product-defined outcomes are not assumed to have a particular response shape or recovery behavior.

# Non-Goals

- No component owns the user's visual review or decides whether a template is ready.
- No component owns an approval ledger or approval enforcement.
- No component owns Wi-Fi credential issuance or populating template placeholders.
- No component owns WifiNote-side persistence or import processing.
- No component owns a general-purpose publishing workflow.

# Architectural Invariants

- A Working Template has one current template code and one Template Manifest describing it.
- Calibrated Placeholder Coordinates refer to the user-supplied design image of the same Working Template.
- A preview is derived from a selected template image and its configuration; rendering does not replace those source inputs.
- An Exported Template contains its Template Manifest, configuration, design image, and image prompt; its preview is included only when one exists.
- Every Template Pack represents all direct template directories selected for that publication under `templates/<code>/`; each packaged template contains only the three files required by WifiNote.
- Every Template Pack has exactly one root Pack Manifest separate from template-level manifests.
- Credentials are used only by the WifiNote publication workflow.
- No upload occurs unless the user explicitly approves the current publication.
- Declining publication leaves WifiNote untouched.
- Prompt Weaver reports upload completion only after the WifiNote Upload Boundary returns success.
- Prompt Weaver does not claim that it performed WifiNote import; import is owned by WifiNote.
- Actual QR code, SSID, and password values are not owned or generated by Prompt Weaver.

# Resolved Decisions

- Human Approval is manual review of every Exported Template, not a recorded or enforced application state, because FR-006 and FR-007 explicitly define those limits.
- The design image is generated outside Prompt Weaver and supplied by the user, because SCR-002 and SCR-003 define a user handoff rather than an application image-generation action.
- Prompt Generation stops after producing `image.prompt`; it does not run image generation, because FR-002 and the SCR-002 interaction distinguish prompt generation from the user's external image-generation step.
- Calibration records placeholder positions only; actual QR code, SSID, and password values are produced by the consuming service, because the Users and Inputs sections assign those values externally.
- Template-level and pack-level manifests are separate artifacts, because FR-001 and FR-034 define the template manifest while FR-014 and FR-015 define the WifiNote archive-root manifest.
- Template Exporter owns carrying `manifest.json` into every Exported Template, because Outputs and FR-034 require that artifact in the exported template.
- Publication Workflow obtains credentials and creates the Template Pack before asking for confirmation, matching the defined publication flow in SCR-009 and the existing command behavior.
- A successful upload ends Prompt Weaver's responsibility; WifiNote stores the upload in its private inbox and owns the separate import process.
- External service behavior is limited to the behavior stated in PRODUCT_SPEC.md; unobserved response details remain marked [UNVERIFIED] rather than being inferred.
