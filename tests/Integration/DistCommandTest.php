<?php

function run_pw_command(array $args, ?string $cwd = null): array
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

function remove_directory_dist(string $directory): void
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

it('copies every config json from fixtures into dist preserving the folder structure', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));
    $distRoot = sys_get_temp_dir().'/prompt-weaver-dist-'.bin2hex(random_bytes(4));

    try {
        mkdir($fixturesRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer', 0777, true);
        mkdir($fixturesRoot.'/gpt-54-mini/wifi-note-cafe', 0777, true);

        file_put_contents($fixturesRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/config.json', '{"model":"gemini-54-flash"}');
        file_put_contents($fixturesRoot.'/gpt-54-mini/wifi-note-cafe/config.json', '{"model":"gpt-54-mini"}');
        file_put_contents($fixturesRoot.'/gpt-54-mini/wifi-note-cafe/design-brief.json', '{"ignored":true}');

        $result = run_pw_command([
            'dist',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$distRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Copied 2 config.json file(s)');

        expect(is_file($distRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/config.json'))->toBeTrue();
        expect(is_file($distRoot.'/gpt-54-mini/wifi-note-cafe/config.json'))->toBeTrue();
        expect(is_file($distRoot.'/gpt-54-mini/wifi-note-cafe/design-brief.json'))->toBeFalse();

        expect(trim((string) file_get_contents($distRoot.'/gemini-54-flash/wifi-warm-cafe-in-summer/config.json')))
            ->toBe('{"model":"gemini-54-flash"}');
        expect(trim((string) file_get_contents($distRoot.'/gpt-54-mini/wifi-note-cafe/config.json')))
            ->toBe('{"model":"gpt-54-mini"}');
    } finally {
        remove_directory_dist($fixturesRoot);
        remove_directory_dist($distRoot);
    }
});
