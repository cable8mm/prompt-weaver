<?php

namespace Workbench\App\Http\Controllers;

use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Cable8mm\PromptWeaver\Pipe;
use Cable8mm\PromptWeaver\Tools\Calibrator;
use Cable8mm\PromptWeaver\Tools\Code;
use Cable8mm\PromptWeaver\Tools\RenderPng;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Workbench\App\Models\TemplateGeneration;

final class PromptWeaverController extends Controller
{
    public function index(): View
    {
        return view('prompt-weaver.index', [
            'generation' => TemplateGeneration::query()->latest()->first(),
            'categories' => Category::cases(),
            'formats' => Format::cases(),
            'colorModes' => ColorMode::cases(),
            'layouts' => Layout::cases(),
        ]);
    }

    public function prepare(): RedirectResponse
    {
        $category = Category::from((string) request('category'));
        $format = Format::from((string) request('format'));
        $colorMode = ColorMode::from((string) request('color_mode'));
        $layout = Layout::from((string) request('layout'));
        $result = app(Pipe::class)->run($category, $format, colorMode: $colorMode, layout: $layout);
        $code = (new Code)->deriveFromTheme((string) data_get($result->config, 'style.theme'));

        $generation = TemplateGeneration::create([
            'code' => $code,
            'category' => $category->value,
            'format' => $format->value,
            'color_mode' => $colorMode->value,
            'layout' => $layout->value,
            'status' => 'image_pending',
            'current_step' => 'image_pending',
            'brief_json' => $result->briefJson,
            'raw_config' => $result->config,
            'image_prompt' => $result->imagePrompt,
        ]);

        return to_route('workbench.prompt-weaver.index', ['generation' => $generation->id]);
    }

    public function upload(TemplateGeneration $generation): RedirectResponse
    {
        $validated = request()->validate([
            'image' => ['required', 'file', 'mimes:png', 'max:20480'],
        ]);
        $path = $validated['image']->storeAs(
            'workbench/generations/'.$generation->id,
            'image.png',
            'public',
        );

        $generation->update([
            'image_path' => $path,
            'status' => 'image_uploaded',
            'current_step' => 'image_uploaded',
        ]);

        return to_route('workbench.prompt-weaver.index', ['generation' => $generation->id]);
    }

    public function calibrate(TemplateGeneration $generation): RedirectResponse
    {
        abort_unless(is_string($generation->image_path), 422, 'Upload an image first.');

        $imagePath = Storage::disk('public')->path($generation->image_path);
        $contents = Storage::disk('public')->get($generation->image_path);
        $image = imagecreatefromstring($contents);

        if ($image === false) {
            abort(422, 'The uploaded image is not a valid image.');
        }

        try {
            $config = (new Calibrator($imagePath))->calibrate($generation->raw_config, $image);
        } finally {
            imagedestroy($image);
        }

        $previewPath = 'workbench/generations/'.$generation->id.'/preview.png';
        (new RenderPng)->render($config, $imagePath, Storage::disk('public')->path($previewPath));

        $generation->update([
            'config' => $config,
            'preview_path' => $previewPath,
            'status' => 'review_pending',
            'current_step' => 'review_pending',
        ]);

        return to_route('workbench.prompt-weaver.index', ['generation' => $generation->id]);
    }

    public function asset(TemplateGeneration $generation, string $asset): mixed
    {
        $path = match ($asset) {
            'image' => $generation->image_path,
            'preview' => $generation->preview_path,
            default => abort(404),
        };

        abort_unless(is_string($path) && Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path));
    }
}
