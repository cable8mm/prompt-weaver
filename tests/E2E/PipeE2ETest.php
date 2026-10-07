<?php

declare(strict_types=1);

use Cable8mm\PromptWeaver\Support\Environment;

/*
|--------------------------------------------------------------------------
| End-to-End Tests (Real API Calls)
|--------------------------------------------------------------------------
| These tests make real API calls to OpenRouter and require:
|   - RUN_E2E_TESTS=1 environment variable
|   - .env with valid API keys and optional provider/model settings
|
| Run with:
|   RUN_E2E_TESTS=1 composer test:e2e
|
| These tests are opt-in and skipped by default. They make real API calls
| and may incur costs.
*/

// Load project-level environment settings, including API keys and model defaults.
Environment::load(dirname(__DIR__, 2).'/.env');

uses()->group('e2e');

$skipE2E = ! getenv('RUN_E2E_TESTS');
$skipMsg = 'Set RUN_E2E_TESTS=1 and configure API keys in .env to run e2e tests.';

/**
 * @param  list<string>  $arguments
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function run_pipe_e2e_command(array $arguments): array
{
    $pipes = [];
    $process = proc_open(
        array_merge([PHP_BINARY, dirname(__DIR__, 2).'/bin/prompt-weaver'], $arguments),
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        dirname(__DIR__, 2),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the Prompt Weaver pipe command.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit_code' => proc_close($process),
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

it('runs the pipe command through the real AI service and saves generated artifacts', function () {
    $provider = getenv('PROMPT_WEAVER_PROVIDER') ?: 'openrouter';
    $model = getenv('PROMPT_WEAVER_MODEL') ?: 'google/gemma-4-26b-a4b-it:free';
    $root = sys_get_temp_dir().'/prompt-weaver-pipe-e2e-'.bin2hex(random_bytes(4));
    $fixture = $root.'/live-template';
    mkdir($fixture, 0777, true);
    file_put_contents($fixture.'/manifest.json', json_encode([
        'code' => 'live-template',
        'category' => 'Cafe/Restaurant',
        'format' => 'A4/A5 Poster',
        'color_mode' => 'Mono',
        'layout' => 'centered',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);

    try {
        $result = run_pipe_e2e_command([
            'pipe',
            'live-template',
            '--fixtures-root='.$root,
            '--provider='.$provider,
            '--model='.$model,
            '--color=warm brown and cream',
            '--no-progress',
        ]);

        expect($result['exit_code'])->toBe(0)
            ->and($result['stdout'].$result['stderr'])->toContain('Pipeline complete.');

        foreach (['brief.prompt', 'design-brief.json', 'config.prompt', 'raw.config.json', 'image.prompt'] as $filename) {
            expect(is_file($fixture.'/'.$filename))->toBeTrue()
                ->and(filesize($fixture.'/'.$filename))->toBeGreaterThan(0);
        }

        $brief = json_decode((string) file_get_contents($fixture.'/design-brief.json'), true, 512, JSON_THROW_ON_ERROR);
        $config = json_decode((string) file_get_contents($fixture.'/raw.config.json'), true, 512, JSON_THROW_ON_ERROR);
        $imagePrompt = (string) file_get_contents($fixture.'/image.prompt');

        expect($brief)
            ->toHaveKey('description')
            ->and($brief['description'])
            ->not->toBeEmpty()
            ->and($config)
            ->toHaveKeys(['canvas', 'style', 'content', 'placeholders'])
            ->and($imagePrompt)
            ->toContain('와이파이 연결');
    } finally {
        foreach (['brief.prompt', 'design-brief.json', 'config.prompt', 'raw.config.json', 'image.prompt', 'manifest.json'] as $filename) {
            if (is_file($fixture.'/'.$filename)) {
                unlink($fixture.'/'.$filename);
            }
        }
        rmdir($fixture);
        rmdir($root);
    }
})->skip($skipE2E, $skipMsg);

it('reports a rejected live AI request and exits non-zero', function () {
    $result = run_pipe_e2e_command([
        'pipe',
        '--category=Cafe/Restaurant',
        '--format=A4/A5 Poster',
        '--provider='.(getenv('PROMPT_WEAVER_PROVIDER') ?: 'openrouter'),
        '--model=prompt-weaver-invalid-model-for-e2e',
        '--no-progress',
    ]);

    expect($result['exit_code'])->not->toBe(0)
        ->and($result['stdout'].$result['stderr'])
        ->toContain('Pipeline failed during design brief generation');
})->skip($skipE2E, $skipMsg);
