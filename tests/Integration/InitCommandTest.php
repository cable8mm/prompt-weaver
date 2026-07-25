<?php

function run_prompt_weaver(array $args, ?string $cwd = null): array
{
    $cwd ??= dirname(__DIR__, 2);

    $command = implode(' ', array_map('escapeshellarg', array_merge(['php', 'bin/prompt-weaver'], $args)));

    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    expect($process)->not->toBeFalse();

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

function remove_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $path) {
        if ($path->isDir()) {
            rmdir($path->getPathname());

            continue;
        }

        unlink($path->getPathname());
    }

    rmdir($directory);
}

it('creates a new fixture manifest with default values', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));

    try {
        $result = run_prompt_weaver([
            'init',
            '--model=gemini-54-flash',
            '--scenario=wifi-warm-cafe-in-summer',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stdout'])->toContain('Created '.$fixturesRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/manifest.json');
        expect($result['stderr'])->toBe('');

        $manifestPath = $fixturesRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/manifest.json';
        expect(is_file($manifestPath))->toBeTrue();

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifestJson = (string) file_get_contents($manifestPath);

        expect($manifest)->toMatchArray([
            'model' => 'gemini-54-flash',
            'scenario' => 'wifi-warm-cafe-in-summer',
            'product' => 'a Wi-Fi signage template',
            'category' => 'Cafe/Restaurant',
            'format' => 'A4/A5 Poster',
        ]);
        expect($manifestJson)
            ->toContain('"category": "Cafe/Restaurant"')
            ->toContain('"format": "A4/A5 Poster"')
            ->not->toContain('\\/');
    } finally {
        remove_directory($fixturesRoot);
    }
});

it('fails when the same model and scenario already exist', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));
    $manifestPath = $fixturesRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/manifest.json';

    try {
        mkdir(dirname($manifestPath), 0777, true);
        file_put_contents($manifestPath, "{}\n");

        $result = run_prompt_weaver([
            'init',
            '--model=gemini-54-flash',
            '--scenario=wifi-warm-cafe-in-summer',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('Fixture already exists');
    } finally {
        remove_directory($fixturesRoot);
    }
});
