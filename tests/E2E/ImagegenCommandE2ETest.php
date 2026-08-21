<?php

declare(strict_types=1);

use Cable8mm\PromptWeaver\Support\Environment;

/*
|--------------------------------------------------------------------------
| Image Generation End-to-End Test (Real API Call)
|--------------------------------------------------------------------------
| This test makes a real image-generation API call and requires:
|   - RUN_E2E_TESTS=1 environment variable
|   - GEMINI_API_KEY in .env (or the provider-specific key)
|
| Run with:
|   RUN_E2E_TESTS=1 composer test:e2e
|
| The provider can be overridden with IMAGEGEN_PROVIDER and the image model
| with IMAGEGEN_MODEL.
*/

Environment::load(dirname(__DIR__, 2).'/.env');

uses()->group('e2e');

$skipE2E = ! getenv('RUN_E2E_TESTS');
$skipMsg = 'Set RUN_E2E_TESTS=1 and configure an image provider API key in .env to run e2e tests.';

it('generates a PNG from image.prompt with the imagegen command', function () {
    $sourcePrompt = dirname(__DIR__).'/Fixtures/cafe-restaurant/image.prompt';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-imagegen-'.bin2hex(random_bytes(4));
    $fixtureDir = $workingRoot.'/cafe-restaurant';
    $outputPath = $fixtureDir.'/image.png';

    mkdir($fixtureDir, 0777, true);
    copy($sourcePrompt, $fixtureDir.'/image.prompt');

    $provider = getenv('IMAGEGEN_PROVIDER') ?: 'gemini';
    $model = getenv('IMAGEGEN_MODEL') ?: ($provider === 'gemini' ? 'gemini-3.1-flash-image-preview' : null);

    $args = [
        'php',
        'bin/prompt-weaver',
        'imagegen',
        'cafe-restaurant',
        '--fixtures-root='.$workingRoot,
        '--provider='.$provider,
    ];

    if (is_string($model) && $model !== '') {
        $args[] = '--model='.$model;
    }

    $command = implode(' ', array_map('escapeshellarg', $args));
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));
    expect($process)->not->toBeFalse();

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    try {
        $processOutput = implode(PHP_EOL, array_filter([
            'Exit code: '.$exitCode,
            $stdout !== '' ? "STDOUT:\n{$stdout}" : null,
            $stderr !== '' ? "STDERR:\n{$stderr}" : null,
        ]));

        expect($exitCode)->toBe(0, "Imagegen command failed.\n{$processOutput}");
        expect($stderr)->toBe('', "Imagegen command wrote to STDERR.\n{$processOutput}");
        expect($stdout)->toContain('Created '.$outputPath, "Imagegen command output was unexpected.\n{$processOutput}");
        expect(is_file($outputPath))->toBeTrue();

        $imageInfo = getimagesize($outputPath);
        expect($imageInfo)->not->toBeFalse("Generated file is not a readable image.\n{$processOutput}");
        expect($imageInfo['mime'] ?? null)->toBe('image/png', "Generated file is not a PNG.\n{$processOutput}");
    } finally {
        remove_imagegen_e2e_directory($workingRoot);
    }
})->skip($skipE2E, $skipMsg);

function remove_imagegen_e2e_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    ) as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }

    rmdir($directory);
}
