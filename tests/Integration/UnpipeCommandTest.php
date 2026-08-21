<?php

function run_prompt_weaver_unpipe(array $args, ?string $cwd = null): array
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

function create_unpipe_fixture(string $root): string
{
    $directory = $root.'/fixture';
    mkdir($directory, 0777, true);

    foreach ([
        'manifest.json',
        'brief.prompt',
        'design-brief.json',
        'config.prompt',
        'raw.config.json',
        'image.prompt',
        'config.json',
        'image.png',
        'preview.png',
    ] as $filename) {
        file_put_contents($directory.'/'.$filename, $filename);
    }

    return $directory;
}

function remove_unpipe_fixture(string $directory): void
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

it('removes all generated files and preserves only the manifest', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-unpipe-'.bin2hex(random_bytes(4));
    $fixture = create_unpipe_fixture($root);

    try {
        $result = run_prompt_weaver_unpipe([
            'unpipe',
            'fixture',
            '--fixtures-root='.$root,
            '--force',
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');

        foreach (['brief.prompt', 'design-brief.json', 'config.prompt', 'raw.config.json', 'image.prompt', 'config.json', 'image.png', 'preview.png'] as $filename) {
            expect(is_file($fixture.'/'.$filename))->toBeFalse();
        }

        expect(is_file($fixture.'/manifest.json'))->toBeTrue();
    } finally {
        remove_unpipe_fixture($root);
    }
});
