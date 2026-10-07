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

it('leaves the working template and export unchanged when the derived code matches', function () {
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-code-'.bin2hex(random_bytes(4));
    $fixturesRoot = $workingRoot.'/fixtures';
    $distRoot = $workingRoot.'/dist';
    $fixtureDirectory = $fixturesRoot.'/wabi-sabi-minimalist';
    $distDirectory = $distRoot.'/wabi-sabi-minimalist';
    mkdir($fixtureDirectory, 0777, true);
    mkdir($distDirectory, 0777, true);
    file_put_contents($fixtureDirectory.'/manifest.json', json_encode(['code' => 'wabi-sabi-minimalist']).PHP_EOL);
    file_put_contents($fixtureDirectory.'/config.json', json_encode([
        'style' => ['theme' => 'Wabi-Sabi Minimalist'],
        'metadata' => ['code' => 'wabi-sabi-minimalist'],
    ]).PHP_EOL);
    file_put_contents($distDirectory.'/config.json', json_encode([
        'metadata' => ['code' => 'wabi-sabi-minimalist'],
    ]).PHP_EOL);
    $manifestBefore = file_get_contents($fixtureDirectory.'/manifest.json');
    $configBefore = file_get_contents($fixtureDirectory.'/config.json');
    $exportConfigBefore = file_get_contents($distDirectory.'/config.json');

    try {
        $result = run_code_command([
            'code',
            'wabi-sabi-minimalist',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$distRoot,
        ]);

        expect($result['exitCode'])->toBe(0)
            ->and(is_dir($fixtureDirectory))->toBeTrue()
            ->and(is_dir($distDirectory))->toBeTrue()
            ->and(file_get_contents($fixtureDirectory.'/manifest.json'))->toBe($manifestBefore)
            ->and(file_get_contents($fixtureDirectory.'/config.json'))->toBe($configBefore)
            ->and(file_get_contents($distDirectory.'/config.json'))->toBe($exportConfigBefore);
    } finally {
        remove_code_command_directory($workingRoot);
    }
});

it('reports missing templates, invalid themes, and missing configuration with non-zero exit codes', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-code-errors-'.bin2hex(random_bytes(4));
    mkdir($fixturesRoot, 0777, true);

    try {
        $missingFixture = run_code_command([
            'code',
            'missing',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$fixturesRoot.'/dist',
        ]);
        expect($missingFixture['exitCode'])->not->toBe(0)
            ->and($missingFixture['stderr'])->toContain('Fixture directory not found');

        foreach ([
            'missing-config' => null,
            'invalid-theme' => ['style' => ['theme' => '---']],
            'missing-theme' => ['style' => []],
        ] as $code => $config) {
            $fixtureDirectory = $fixturesRoot.'/'.$code;
            mkdir($fixtureDirectory);
            file_put_contents($fixtureDirectory.'/manifest.json', json_encode(['code' => $code]).PHP_EOL);

            if ($config !== null) {
                file_put_contents($fixtureDirectory.'/config.json', json_encode($config).PHP_EOL);
            }

            $result = run_code_command([
                'code',
                $code,
                '--fixtures-root='.$fixturesRoot,
                '--dist-root='.$fixturesRoot.'/dist',
            ]);

            expect($result['exitCode'])->not->toBe(0);
            expect($result['stderr'])->toContain(
                $config === null
                    ? 'JSON file not found'
                    : ($code === 'invalid-theme' ? 'Unable to derive a code from style.theme' : "Config field 'style.theme' is missing or invalid"),
            );
        }
    } finally {
        remove_code_command_directory($fixturesRoot);
    }
});

it('fails without changing the working template when the destination export code already exists', function () {
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-code-dist-collision-'.bin2hex(random_bytes(4));
    $fixturesRoot = $workingRoot.'/fixtures';
    $distRoot = $workingRoot.'/dist';
    $source = $fixturesRoot.'/old-code';
    mkdir($source, 0777, true);
    mkdir($distRoot.'/new-theme', 0777, true);
    file_put_contents($source.'/manifest.json', json_encode(['code' => 'old-code']).PHP_EOL);
    file_put_contents($source.'/config.json', json_encode([
        'style' => ['theme' => 'New Theme'],
    ]).PHP_EOL);
    $manifestBefore = file_get_contents($source.'/manifest.json');
    $configBefore = file_get_contents($source.'/config.json');

    try {
        $result = run_code_command([
            'code',
            'old-code',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$distRoot,
        ]);

        expect($result['exitCode'])->not->toBe(0)
            ->and($result['stderr'])->toContain('Export directory already exists')
            ->and(is_dir($source))->toBeTrue()
            ->and(file_get_contents($source.'/manifest.json'))->toBe($manifestBefore)
            ->and(file_get_contents($source.'/config.json'))->toBe($configBefore);
    } finally {
        remove_code_command_directory($workingRoot);
    }
});

it('limits the derived code to four words', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-code-'.bin2hex(random_bytes(4));
    $sourceDirectory = $fixturesRoot.'/old-code';
    mkdir($sourceDirectory, 0777, true);
    file_put_contents($sourceDirectory.'/manifest.json', json_encode(['code' => 'old-code']).PHP_EOL);
    file_put_contents($sourceDirectory.'/config.json', json_encode([
        'style' => ['theme' => 'A timeless industrial cafe mood blending vintage concrete texture'],
    ]).PHP_EOL);

    try {
        $result = run_code_command([
            'code',
            'old-code',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$fixturesRoot.'/dist',
        ]);

        expect($result['exitCode'])->toBe(0);
        expect(is_dir($fixturesRoot.'/a-timeless-industrial-cafe'))->toBeTrue();
    } finally {
        remove_code_command_directory($fixturesRoot);
    }
});

it('renames every fixture with code-all after validating the complete batch', function () {
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-code-all-'.bin2hex(random_bytes(4));
    $fixturesRoot = $workingRoot.'/fixtures';
    $distRoot = $workingRoot.'/dist';

    foreach (['first', 'second'] as $code) {
        mkdir($fixturesRoot.'/'.$code, 0777, true);
        file_put_contents($fixturesRoot.'/'.$code.'/manifest.json', json_encode(['code' => $code]).PHP_EOL);
    }

    file_put_contents($fixturesRoot.'/first/config.json', json_encode([
        'style' => ['theme' => 'Warm Linen Cafe'],
    ]).PHP_EOL);
    file_put_contents($fixturesRoot.'/second/config.json', json_encode([
        'style' => ['theme' => 'Modern Quiet Office'],
    ]).PHP_EOL);
    mkdir($distRoot.'/first', 0777, true);
    file_put_contents($distRoot.'/first/config.json', json_encode([
        'metadata' => ['code' => 'first'],
    ]).PHP_EOL);

    try {
        $dryRun = run_code_command([
            'code-all',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$distRoot,
            '--dry-run',
        ]);

        expect($dryRun['exitCode'])->toBe(0);
        expect($dryRun['stdout'])->toContain('first -> warm-linen-cafe');
        expect($dryRun['stdout'])->toContain('second -> modern-quiet-office');
        expect(is_dir($fixturesRoot.'/first'))->toBeTrue();

        $result = run_code_command([
            'code-all',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$distRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect(is_dir($fixturesRoot.'/warm-linen-cafe'))->toBeTrue();
        expect(is_dir($fixturesRoot.'/modern-quiet-office'))->toBeTrue();
        expect(is_dir($distRoot.'/warm-linen-cafe'))->toBeTrue();
        expect(is_dir($fixturesRoot.'/first'))->toBeFalse();
    } finally {
        remove_code_command_directory($workingRoot);
    }
});

it('does not change any fixture when code-all finds duplicate derived codes', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-code-all-'.bin2hex(random_bytes(4));

    foreach (['first', 'second'] as $code) {
        mkdir($fixturesRoot.'/'.$code, 0777, true);
        file_put_contents($fixturesRoot.'/'.$code.'/manifest.json', json_encode(['code' => $code]).PHP_EOL);
        file_put_contents($fixturesRoot.'/'.$code.'/config.json', json_encode([
            'style' => ['theme' => 'Same Theme'],
        ]).PHP_EOL);
    }

    try {
        $result = run_code_command([
            'code-all',
            '--fixtures-root='.$fixturesRoot,
            '--dist-root='.$fixturesRoot.'/dist',
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('Multiple fixtures derive the same code');
        expect(is_dir($fixturesRoot.'/first'))->toBeTrue();
        expect(is_dir($fixturesRoot.'/second'))->toBeTrue();
    } finally {
        remove_code_command_directory($fixturesRoot);
    }
});
