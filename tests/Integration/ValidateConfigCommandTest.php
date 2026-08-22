<?php

function run_prompt_weaver_config_validate(array $args, ?string $cwd = null): array
{
    $cwd ??= dirname(__DIR__, 2);
    $command = implode(' ', array_map('escapeshellarg', array_merge(['php', 'bin/prompt-weaver'], $args)));
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, $cwd);

    expect($process)->not->toBeFalse();
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exitCode' => proc_close($process),
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

it('validates a config JSON file from the command line', function () {
    $path = dirname(__DIR__).'/Fixtures/cafe-restaurant/config.json';

    $result = run_prompt_weaver_config_validate(['config:validate', $path]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stdout'])->toContain('Config is valid.');
    expect($result['stderr'])->toBe('');
});

it('returns an error for an invalid config JSON file', function () {
    $path = tempnam(sys_get_temp_dir(), 'prompt-weaver-config-');
    file_put_contents($path, json_encode([
        'canvas' => ['aspect_ratio' => 'invalid'],
        'style' => [],
        'content' => [],
        'placeholders' => [],
    ]));

    try {
        $result = run_prompt_weaver_config_validate(['config:validate', $path]);

        expect($result['exitCode'])->toBe(1);
        expect($result['stderr'])->toContain('Config has an invalid canvas.aspect_ratio');
    } finally {
        unlink($path);
    }
});
