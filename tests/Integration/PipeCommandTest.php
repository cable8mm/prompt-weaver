<?php

use Cable8mm\PromptWeaver\Console\Commands\PipeCommand;
use Cable8mm\PromptWeaver\Contracts\AiClient;
use Symfony\Component\Console\Tester\CommandTester;

final class PipeCommandFakeAiClient implements AiClient
{
    /** @var list<array{prompt: string, provider: ?string, model: ?string}> */
    public array $requests = [];

    public function structured(string $prompt, Closure $schema, ?string $provider = null, ?string $model = null): array
    {
        $this->requests[] = compact('prompt', 'provider', 'model');

        if (str_contains($prompt, '[Role]') && str_contains($prompt, 'creative director')) {
            return [
                'name' => 'Test design',
                'description' => 'A test Wi-Fi sign.',
                'color_direction' => 'blue and cream',
                'font_mood' => 'clean sans-serif',
            ];
        }

        return [
            'canvas' => [
                'width_pc' => 100, 'height_pc' => 100, 'aspect_ratio' => '5:7',
                'width_mm' => 210, 'height_mm' => 297, 'dpi' => 300,
            ],
            'style' => [
                'theme' => 'test theme',
                'background' => 'cream',
                'print_target' => 'black-and-white laser printer safe',
            ],
            'metadata' => ['style' => ['theme' => 'test theme', 'background' => 'cream']],
            'content' => [
                'title' => ['text' => 'Test', 'x_pc' => 50, 'y_pc' => 10, 'align' => 'center', 'style' => 'bold'],
                'wifi_icon' => ['x_pc' => 50, 'y_pc' => 20, 'width_pc' => 15, 'style' => 'simple'],
            ],
            'placeholders' => [
                'ssid' => [
                    'box_x_pc' => 50, 'box_y_pc' => 40, 'box_width_pc' => 70, 'box_height_pc' => 8,
                    'label' => 'SSID:', 'label_position' => 'outside_above',
                    'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid white',
                ],
                'password' => [
                    'box_x_pc' => 50, 'box_y_pc' => 52, 'box_width_pc' => 70, 'box_height_pc' => 8,
                    'label' => 'PASSWORD:', 'label_position' => 'outside_above',
                    'box_fill' => '#FFFFFF', 'box_fill_note' => 'solid white',
                ],
                'qr' => ['x_pc' => 50, 'y_pc' => 80, 'width_pc' => 28, 'style' => 'clean square'],
            ],
        ];
    }

    public function text(string $prompt, ?string $provider = null, ?string $model = null): string
    {
        return '';
    }

    public function image(string $prompt, ?string $provider = null, ?string $model = null, ?string $size = null): string
    {
        return '';
    }
}

function run_pipe_command_test(array $arguments, PipeCommandFakeAiClient $client): array
{
    app()->instance(AiClient::class, $client);
    $tester = new CommandTester(new PipeCommand);
    $exitCode = $tester->execute($arguments, ['interactive' => false]);

    return [$exitCode, $tester->getDisplay()];
}

it('writes all generated artifacts to the selected working template', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-pipe-'.bin2hex(random_bytes(4));
    $fixture = $root.'/custom-template';
    mkdir($fixture, 0777, true);
    file_put_contents($fixture.'/manifest.json', json_encode([
        'code' => 'custom-template',
        'category' => 'Cafe/Restaurant',
        'format' => 'A4/A5 Poster',
        'color_mode' => 'Mono',
        'layout' => 'centered',
    ], JSON_THROW_ON_ERROR));

    $client = new PipeCommandFakeAiClient;
    [$exitCode, $consoleOutput] = run_pipe_command_test([
        'fixture' => 'custom-template',
        '--fixtures-root' => $root,
        '--provider' => 'openrouter',
        '--model' => 'test-model',
        '--no-progress' => true,
    ], $client);

    expect($exitCode)->toBe(0)
        ->and($consoleOutput)->toContain('Pipeline complete.')
        ->and($consoleOutput)->not->toContain('Generating design brief...');

    foreach (['brief.prompt', 'design-brief.json', 'config.prompt', 'raw.config.json', 'image.prompt'] as $filename) {
        expect(is_file($fixture.'/'.$filename))->toBeTrue();
    }

    expect(json_decode((string) file_get_contents($fixture.'/design-brief.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toHaveKey('description', 'A test Wi-Fi sign.');
    expect(json_decode((string) file_get_contents($fixture.'/raw.config.json'), true, 512, JSON_THROW_ON_ERROR))
        ->toHaveKey('style');
    expect($client->requests)->toHaveCount(2)
        ->and($client->requests[0]['provider'])->toBe('openrouter')
        ->and($client->requests[0]['model'])->toBe('test-model')
        ->and($client->requests[1]['provider'])->toBe('openrouter')
        ->and($client->requests[1]['model'])->toBe('test-model');

    foreach (['brief.prompt', 'design-brief.json', 'config.prompt', 'raw.config.json', 'image.prompt', 'manifest.json'] as $filename) {
        unlink($fixture.'/'.$filename);
    }
    rmdir($fixture);
    rmdir($root);
});

it('prints generated prompts and responses only when show-output is supplied without a template', function () {
    $client = new PipeCommandFakeAiClient;
    [$exitCode, $consoleOutput] = run_pipe_command_test([
        '--category' => 'Office/Coworking',
        '--format' => 'A6/A7 Poster',
        '--color-mode' => 'Color',
        '--layout' => 'editorial',
        '--provider' => 'openrouter',
        '--api-key' => 'test-api-key',
        '--model' => 'test-model',
        '--color' => 'ocean blue and coral',
        '--fixtures-root' => sys_get_temp_dir(),
        '--no-progress' => true,
        '--show-output' => true,
    ], $client);

    expect($exitCode)->toBe(0)
        ->and($consoleOutput)
        ->toContain('=== design-brief prompt ===')
        ->toContain('=== design-brief response ===')
        ->toContain('=== config prompt ===')
        ->toContain('=== config response ===')
        ->toContain('=== image prompt ===')
        ->toContain('test theme')
        ->not->toContain('test-api-key');

    expect($client->requests[0]['prompt'])
        ->toContain('Office/Coworking')
        ->toContain('A6/A7 Poster')
        ->toContain('ocean blue and coral');
    expect($client->requests[1]['prompt'])
        ->toContain('- Color Mode: Color')
        ->toContain('asymmetrical typography');
    expect(config('ai.providers.openrouter.key'))->toBe('test-api-key');
});

it('does not print generated prompts and responses without show-output', function () {
    $client = new PipeCommandFakeAiClient;
    [$exitCode, $consoleOutput] = run_pipe_command_test([
        '--category' => 'Cafe/Restaurant',
        '--format' => 'A4/A5 Poster',
        '--no-progress' => true,
    ], $client);

    expect($exitCode)->toBe(0)
        ->and($consoleOutput)->not->toContain('=== design-brief prompt ===')
        ->and($consoleOutput)->toContain('Use a fixture argument to save the generated files.');
});
