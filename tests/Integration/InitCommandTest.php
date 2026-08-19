<?php

function run_prompt_weaver(array $args, ?string $cwd = null): array
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
            'wifi-warm-cafe-in-summer',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stdout'])->toContain('Created '.$fixturesRoot.'/wifi-warm-cafe-in-summer/manifest.json');
        expect($result['stderr'])->toBe('');

        $manifestPath = $fixturesRoot.'/wifi-warm-cafe-in-summer/manifest.json';
        expect(is_file($manifestPath))->toBeTrue();

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $manifestJson = (string) file_get_contents($manifestPath);

        expect($manifest)->toMatchArray([
            'code' => 'wifi-warm-cafe-in-summer',
            'category' => 'Cafe/Restaurant',
            'format' => 'A4/A5 Poster',
            'color_mode' => 'mono',
        ]);
        expect($manifestJson)
            ->toContain('"category": "Cafe/Restaurant"')
            ->toContain('"format": "A4/A5 Poster"')
            ->not->toContain('\\/');
    } finally {
        remove_directory($fixturesRoot);
    }
});

it('creates a new fixture manifest with custom category and format', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));

    try {
        $result = run_prompt_weaver([
            'init',
            'wifi-warm-cafe-in-summer',
            '--category=Office/Coworking',
            '--format=A6/A7 Poster',
            '--color-mode=color',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');

        $manifestPath = $fixturesRoot.'/wifi-warm-cafe-in-summer/manifest.json';
        expect(is_file($manifestPath))->toBeTrue();

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        expect($manifest)->toMatchArray([
            'code' => 'wifi-warm-cafe-in-summer',
            'category' => 'Office/Coworking',
            'format' => 'A6/A7 Poster',
            'color_mode' => 'color',
        ]);
    } finally {
        remove_directory($fixturesRoot);
    }
});

it('shows available categories and formats in help output', function () {
    $result = run_prompt_weaver([
        'help',
    ]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stdout'])
        ->toContain('Available categories')
        ->toContain('Cafe/Restaurant, Office/Coworking, Stay/Hotel, Event/Exhibition, Other')
        ->toContain('Available formats')
        ->toContain('A4/A5 Poster, A6/A7 Poster, Mini Square');
});

it('shows valid categories in error message for an unknown category', function () {
    $result = run_prompt_weaver([
        'brief',
        '--category=Unknown Category',
        '--format=A4/A5 Poster',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('Unknown category')
        ->and($result['stderr'])->toContain('Cafe/Restaurant, Office/Coworking, Stay/Hotel, Event/Exhibition, Other');
});

it('shows valid formats in error message for an unknown format', function () {
    $result = run_prompt_weaver([
        'brief',
        '--category=Cafe/Restaurant',
        '--format=Unknown Format',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('Unknown format')
        ->and($result['stderr'])->toContain('A4/A5 Poster, A6/A7 Poster, Mini Square');
});

it('fails when the same template code already exists', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));
    $manifestPath = $fixturesRoot.'/wifi-warm-cafe-in-summer/manifest.json';

    try {
        mkdir(dirname($manifestPath), 0777, true);
        file_put_contents($manifestPath, "{}\n");

        $result = run_prompt_weaver([
            'init',
            'wifi-warm-cafe-in-summer',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('Fixture already exists');
    } finally {
        remove_directory($fixturesRoot);
    }
});

it('rejects an unknown category or format before writing a manifest', function () {
    $fixturesRoot = sys_get_temp_dir().'/prompt-weaver-fixtures-'.bin2hex(random_bytes(4));
    $fixtureDirectory = $fixturesRoot.'/invalid-fixture';

    try {
        $result = run_prompt_weaver([
            'init',
            'invalid-fixture',
            '--category=Unknown',
            '--format=Unknown',
            '--fixtures-root='.$fixturesRoot,
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('Unknown category');
        expect(is_file($fixtureDirectory.'/manifest.json'))->toBeFalse();
    } finally {
        remove_directory($fixturesRoot);
    }
});
