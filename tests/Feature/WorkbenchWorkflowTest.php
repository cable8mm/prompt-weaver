<?php

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\TemplateGeneration;
use Workbench\App\Services\FixtureAiClient;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->singleton(AiClient::class, FixtureAiClient::class);
});

it('runs the Laravel prompt, upload, calibration, and preview workflow', function () {
    Storage::fake('public');

    $response = $this->post(route('workbench.prompt-weaver.prepare'), [
        'category' => 'Cafe/Restaurant',
        'format' => 'A4/A5 Poster',
        'color_mode' => 'Mono',
        'layout' => 'centered',
    ]);

    $response->assertRedirect();

    $generation = TemplateGeneration::query()->firstOrFail();

    expect($generation->status)->toBe('image_pending')
        ->and($generation->code)->not->toBeEmpty()
        ->and($generation->image_prompt)->not->toBeEmpty();

    $uploadResponse = $this->post(route('workbench.prompt-weaver.upload', $generation), [
        'image' => UploadedFile::fake()
            ->createWithContent('image.png', (string) file_get_contents(dirname(__DIR__).'/Fixtures/cafe-restaurant/image.png'))
            ->mimeType('image/png'),
    ]);

    $uploadResponse->assertRedirect();
    $uploadResponse->assertSessionHasNoErrors();
    $generation->refresh();

    expect($generation->status)->toBe('image_uploaded');
    Storage::disk('public')->assertExists($generation->image_path);

    $calibrateResponse = $this->post(route('workbench.prompt-weaver.calibrate', $generation));

    $calibrateResponse->assertRedirect();
    $generation->refresh();

    expect($generation->status)->toBe('review_pending')
        ->and($generation->config)->toBeArray()
        ->and($generation->preview_path)->not->toBeEmpty();
    Storage::disk('public')->assertExists($generation->preview_path);
});
