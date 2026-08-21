<?php

function run_code_command(array $args): array
{
    $command = implode(' ', array_map('escapeshellarg', array_merge(['php', dirname(__DIR__, 2).'/bin/prompt-weaver'], $args)));
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 2));

    expect($process)->not->toBeFalse();

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

function remove_code_command_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }

    rmdir($directory);
}

it('renames a fixture using its config theme and updates its export', function () {
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-code-'.bin2hex(random_bytes(4));
    $fixtureDirectory = $workingRoot.'/fixtures/old-code';
    $distDirectory = $workingRoot.'/dist/old-code';
    mkdir($fixtureDirectory, 0777, true);
    mkdir($distDirectory, 0777, true);

    file_put_contents($fixtureDirectory.'/manifest.json', json_encode(['code' => 'old-code']).PHP_EOL);
    file_put_contents($fixtureDirectory.'/config.json', json_encode([
        'style' => ['theme' => 'Wabi-Sabi Minimalist'],
    ]).PHP_EOL);
    file_put_contents($distDirectory.'/config.json', json_encode([
        'metadata' => ['code' => 'old-code'],
    ]).PHP_EOL);

    try {
        $result = run_code_command([
            'code',
            'old-code',
            '--fixtures-root='.$workingRoot.'/fixtures',
            '--dist-root='.$workingRoot.'/dist',
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect(is_dir($workingRoot.'/fixtures/wabi-sabi-minimalist'))->toBeTrue();
        expect(is_dir($workingRoot.'/dist/wabi-sabi-minimalist'))->toBeTrue();
        expect(is_dir($fixtureDirectory))->toBeFalse();

        $manifest = json_decode((string) file_get_contents($workingRoot.'/fixtures/wabi-sabi-minimalist/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $config = json_decode((string) file_get_contents($workingRoot.'/fixtures/wabi-sabi-minimalist/config.json'), true, 512, JSON_THROW_ON_ERROR);
        $exportedConfig = json_decode((string) file_get_contents($workingRoot.'/dist/wabi-sabi-minimalist/config.json'), true, 512, JSON_THROW_ON_ERROR);

        expect($manifest['code'])->toBe('wabi-sabi-minimalist');
        expect($config)->not->toHaveKey('metadata');
        expect($exportedConfig['metadata']['code'])->toBe('wabi-sabi-minimalist');
    } finally {
        remove_code_command_directory($workingRoot);
    }
});

it('fails when the derived code already exists', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-code-'.bin2hex(random_bytes(4));
    $sourceDirectory = $fixturesRoot.'/old-code';
    mkdir($sourceDirectory, 0777, true);
    mkdir($fixturesRoot.'/wabi-sabi-minimalist', 0777, true);
    file_put_contents($sourceDirectory.'/manifest.json', json_encode(['code' => 'old-code']).PHP_EOL);
    file_put_contents($sourceDirectory.'/config.json', json_encode([
        'style' => ['theme' => 'Wabi-Sabi Minimalist'],
    ]).PHP_EOL);

    try {
        $result = run_code_command([
            'code',
            'old-code',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$fixturesRoot.'/dist',
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('Fixture already exists');
    } finally {
        remove_code_command_directory($fixturesRoot);
    }
});
