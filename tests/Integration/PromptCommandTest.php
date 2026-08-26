<?php

function run_prompt_weaver_cmd(array $args, ?string $cwd = null): array
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

function copy_directory_cmd(string $source, string $target): void
{
    if (! is_dir($target) && ! mkdir($target, 0777, true) && ! is_dir($target)) {
        throw new RuntimeException("Unable to create directory: {$target}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $destination = $target.'/'.substr($item->getPathname(), strlen($source) + 1);

        if ($item->isDir()) {
            if (! is_dir($destination) && ! mkdir($destination, 0777, true) && ! is_dir($destination)) {
                throw new RuntimeException("Unable to create directory: {$destination}");
            }

            continue;
        }

        if (! is_dir(dirname($destination)) && ! mkdir(dirname($destination), 0777, true) && ! is_dir(dirname($destination))) {
            throw new RuntimeException('Unable to create directory: '.dirname($destination));
        }

        copy($item->getPathname(), $destination);
    }
}

function remove_directory_cmd(string $directory): void
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

it('generates a design brief prompt from fixture and saves to brief.prompt', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-brief-'.bin2hex(random_bytes(4));
    $fixtureDir = $workingFixture.'/cafe-restaurant';

    try {
        copy_directory_cmd($sourceFixture, $fixtureDir);

        $result = run_prompt_weaver_cmd([
            'brief',
            'cafe-restaurant',
            '--fixtures-root='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');

        $promptPath = $fixtureDir.'/brief.prompt';
        expect($result['stdout'])->toContain('Created '.$promptPath);
        expect(is_file($promptPath))->toBeTrue();
        expect(file_get_contents($promptPath))->not->toBeEmpty();
    } finally {
        remove_directory_cmd($workingFixture);
    }
});

it('generates a design brief prompt from direct options', function () {
    $result = run_prompt_weaver_cmd([
        'brief',
        '--category=Cafe/Restaurant',
        '--format=A4/A5 Poster',
    ]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->not->toBeEmpty()->and($result['stdout'])->toContain('design brief');
});

it('fails brief command when required options are missing', function () {
    $result = run_prompt_weaver_cmd([
        'brief',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('must be of type string');
});

it('generates a config prompt from fixture and saves to config.prompt', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-config-'.bin2hex(random_bytes(4));
    $fixtureDir = $workingFixture.'/cafe-restaurant';

    try {
        copy_directory_cmd($sourceFixture, $fixtureDir);

        $result = run_prompt_weaver_cmd([
            'config',
            'cafe-restaurant',
            '--fixtures-root='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');

        $promptPath = $fixtureDir.'/config.prompt';
        expect($result['stdout'])->toContain('Created '.$promptPath);
        expect(is_file($promptPath))->toBeTrue();
        expect(file_get_contents($promptPath))->not->toBeEmpty();
    } finally {
        remove_directory_cmd($workingFixture);
    }
});

it('generates a config prompt from direct options', function () {
    $result = run_prompt_weaver_cmd([
        'config',
        '--description=A warm and inviting cafe poster',
        '--color-direction=charcoal, ivory, and warm gray',
        '--font-mood=elegant rounded sans-serif',
        '--format=A4/A5 Poster',
    ]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->not->toBeEmpty();
});

it('fails config command when required options are missing', function () {
    $result = run_prompt_weaver_cmd([
        'config',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('must be of type string');
});

it('generates an image prompt from fixture and saves to image.prompt', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-image-'.bin2hex(random_bytes(4));
    $fixtureDir = $workingFixture.'/cafe-restaurant';

    try {
        copy_directory_cmd($sourceFixture, $fixtureDir);

        $result = run_prompt_weaver_cmd([
            'image',
            'cafe-restaurant',
            '--fixtures-root='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');

        $promptPath = $fixtureDir.'/image.prompt';
        expect($result['stdout'])->toContain('Created '.$promptPath);
        expect(is_file($promptPath))->toBeTrue();
        expect(file_get_contents($promptPath))->not->toBeEmpty();
    } finally {
        remove_directory_cmd($workingFixture);
    }
});

it('generates an image prompt from a config file', function () {
    $configPath = dirname(__DIR__).'/Fixtures/cafe-restaurant/raw.config.json';

    $result = run_prompt_weaver_cmd([
        'image',
        '--config-file='.$configPath,
    ]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->not->toBeEmpty();
});

it('fails image command when config file is missing', function () {
    $result = run_prompt_weaver_cmd([
        'image',
        '--config-file=/tmp/non-existent-config.json',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('Config file not found');
});

it('runs the chain command with all required options', function () {
    $configPath = dirname(__DIR__).'/Fixtures/cafe-restaurant/raw.config.json';

    $result = run_prompt_weaver_cmd([
        'chain',
        '--category=Cafe/Restaurant',
        '--format=A4/A5 Poster',
        '--description=A warm and inviting cafe poster featuring elegant Art Deco geometry',
        '--color-direction=charcoal, ivory, and warm gray with optional muted blush accents',
        '--font-mood=elegant rounded sans-serif with subtle Art Deco influence',
        '--config-file='.$configPath,
    ]);

    expect($result['exitCode'])->toBe(0);
    expect($result['stderr'])->toBe('');
    expect($result['stdout'])->toContain('=== design-brief prompt ===');
    expect($result['stdout'])->toContain('=== config prompt ===');
    expect($result['stdout'])->toContain('=== image prompt ===');
});

it('fails chain command when required options are missing', function () {
    $result = run_prompt_weaver_cmd([
        'chain',
    ]);

    expect($result['exitCode'])->not->toBe(0);
    expect($result['stderr'])->toContain('must be of type string');
});

it('exports a generated png and config for Laravel import', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-export-'.bin2hex(random_bytes(4));
    $fixtureDirectory = $workingRoot.'/fixtures/cafe-restaurant';
    $outputDirectory = $workingRoot.'/dist/cafe-restaurant';

    try {
        copy_directory_cmd($sourceFixture, $fixtureDirectory);

        $result = run_prompt_weaver_cmd([
            'export',
            'cafe-restaurant',
            '--fixtures-root='.$workingRoot.'/fixtures',
            '--image='.$fixtureDirectory.'/image.png',
            '--output-dir='.$outputDirectory,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Created '.$outputDirectory);
        expect(is_file($outputDirectory.'/config.json'))->toBeTrue();
        expect(is_file($outputDirectory.'/image.png'))->toBeTrue();
        expect(is_file($outputDirectory.'/image.prompt'))->toBeTrue();
        expect(is_file($outputDirectory.'/preview.png'))->toBeTrue();
        expect(is_file($outputDirectory.'/manifest.json'))->toBeFalse();
        $exportedConfig = json_decode((string) file_get_contents($outputDirectory.'/config.json'), true, 512, JSON_THROW_ON_ERROR);

        expect(array_slice(array_keys($exportedConfig), 0, 2))->toBe(['schema_version', 'metadata']);
        expect($exportedConfig)
            ->toMatchArray([
                'schema_version' => 1,
                'metadata' => [
                    'code' => 'cafe-restaurant',
                    'category' => 'Cafe/Restaurant',
                    'format' => 'A4/A5 Poster',
                    'color_mode' => 'Mono',
                    'name' => '벚꽃 아르데코',
                    'description' => '은은한 벚꽃 장식과 정교한 기하학적 라인 패턴을 더한 따뜻하고 편안한 카페 분위기의 포스터입니다. 넉넉한 여백과 선명한 테두리를 사용해 모노크롬 인쇄에서도 잘 보이도록 구성합니다.',
                    'color_direction' => '차콜, 아이보리, 웜 그레이를 중심으로 하고 회색조에서도 구분되는 은은한 블러시 포인트를 더합니다.',
                    'font_mood' => '은은한 아르데코 감성을 더한 우아하고 둥근 산세리프 한글 글꼴',
                ],
            ]);
        expect(md5_file($outputDirectory.'/image.png'))->toBe(md5_file($fixtureDirectory.'/image.png'));
        expect(md5_file($outputDirectory.'/image.prompt'))->toBe(md5_file($fixtureDirectory.'/image.prompt'));
        expect(md5_file($outputDirectory.'/preview.png'))->toBe(md5_file($fixtureDirectory.'/preview.png'));
    } finally {
        remove_directory_cmd($workingRoot);
    }
});

it('exports every fixture with export-all', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-export-all-'.bin2hex(random_bytes(4));
    $fixturesRoot = $workingRoot.'/fixtures';
    $outputRoot = $workingRoot.'/dist';

    try {
        copy_directory_cmd($sourceFixture, $fixturesRoot.'/cafe-restaurant');
        copy_directory_cmd($sourceFixture, $fixturesRoot.'/office-coworking');

        $officeManifest = json_decode(
            (string) file_get_contents($fixturesRoot.'/office-coworking/manifest.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $officeManifest['code'] = 'office-coworking';
        file_put_contents(
            $fixturesRoot.'/office-coworking/manifest.json',
            json_encode($officeManifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        $result = run_prompt_weaver_cmd([
            'export-all',
            '--fixtures-root='.$fixturesRoot,
            '--output-dir='.$outputRoot,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Created '.$outputRoot.'/cafe-restaurant');
        expect($result['stdout'])->toContain('Created '.$outputRoot.'/office-coworking');
        expect(is_file($outputRoot.'/cafe-restaurant/config.json'))->toBeTrue();
        expect(is_file($outputRoot.'/cafe-restaurant/image.png'))->toBeTrue();
        expect(is_file($outputRoot.'/cafe-restaurant/image.prompt'))->toBeTrue();
        expect(is_file($outputRoot.'/office-coworking/config.json'))->toBeTrue();
        expect(is_file($outputRoot.'/office-coworking/image.png'))->toBeTrue();
        expect(is_file($outputRoot.'/office-coworking/image.prompt'))->toBeTrue();
    } finally {
        remove_directory_cmd($workingRoot);
    }
});

it('rejects an exported image with the wrong aspect ratio', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-export-'.bin2hex(random_bytes(4));
    $fixtureDirectory = $workingRoot.'/fixtures/cafe-restaurant';
    $outputDirectory = $workingRoot.'/dist/cafe-restaurant';
    $wrongImage = $workingRoot.'/wrong.png';

    try {
        copy_directory_cmd($sourceFixture, $fixtureDirectory);
        $wrongImageResource = imagecreatetruecolor(100, 100);
        imagepng($wrongImageResource, $wrongImage);
        imagedestroy($wrongImageResource);

        $result = run_prompt_weaver_cmd([
            'export',
            'cafe-restaurant',
            '--fixtures-root='.$workingRoot.'/fixtures',
            '--image='.$wrongImage,
            '--output-dir='.$outputDirectory,
        ]);

        expect($result['exitCode'])->not->toBe(0);
        expect($result['stderr'])->toContain('aspect ratio');
        expect(is_file($outputDirectory.'/image.png'))->toBeFalse();
    } finally {
        remove_directory_cmd($workingRoot);
    }
});
