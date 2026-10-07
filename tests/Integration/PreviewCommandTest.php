<?php

function run_prompt_weaver_preview(array $args, ?string $cwd = null): array
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

function copy_directory_preview(string $source, string $target): void
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

function remove_directory_preview(string $directory): void
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

it('creates a preview image by overlaying qr and credential text on the background', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-preview-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $configPath = $workingFixture.'/config.json';
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $config['placeholders']['qr']['x_pc'] = 50;
        $config['placeholders']['qr']['y_pc'] = 80;
        $config['placeholders']['qr']['width_pc'] = 28;
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT).PHP_EOL);
        $configBeforePreview = file_get_contents($configPath);

        $outputPath = $workingFixture.'/preview.png';

        $result = run_prompt_weaver_preview([
            'preview',
            '--fixture='.$workingFixture,
            '--output='.$outputPath,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Created '.$outputPath);
        expect(is_file($outputPath))->toBeTrue();
        expect(file_get_contents($configPath))->toBe($configBeforePreview);

        [$baseWidth, $baseHeight] = getimagesize($workingFixture.'/image.png');
        [$previewWidth, $previewHeight] = getimagesize($outputPath);

        expect([$previewWidth, $previewHeight])->toBe([$baseWidth, $baseHeight]);
        expect(md5_file($outputPath))->not->toBe(md5_file($workingFixture.'/image.png'));

        $preview = imagecreatefrompng($outputPath);
        expect($preview)->toBeInstanceOf(GdImage::class);

        // QR uses x_pc/y_pc/width_pc and should be rendered in the configured
        // lower-center area, not at the image origin.
        $darkPixels = 0;
        for ($y = 1000; $y < 1300; $y++) {
            for ($x = 390; $x < 700; $x++) {
                $color = imagecolorat($preview, $x, $y) & 0xFFFFFF;

                if ($color < 0x333333) {
                    $darkPixels++;
                }
            }
        }

        expect($darkPixels)->toBeGreaterThan(1000);
        imagedestroy($preview);
    } finally {
        remove_directory_preview($workingFixture);
    }
});

it('renders the default PNG preview when a template code and fixtures root are supplied', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-preview-default-'.bin2hex(random_bytes(4));
    $workingFixture = $workingRoot.'/preview-target';

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $result = run_prompt_weaver_preview([
            'preview',
            'preview-target',
            '--fixtures-root='.$workingRoot,
        ]);

        expect($result['exitCode'])->toBe(0)
            ->and($result['stderr'])->toBe('')
            ->and($result['stdout'])->toContain('Created '.$workingFixture.'/preview.png')
            ->and(is_file($workingFixture.'/preview.png'))->toBeTrue()
            ->and(getimagesize($workingFixture.'/preview.png'))->not->toBeFalse();
    } finally {
        remove_directory_preview($workingRoot);
    }
});

it('renders an HTML preview to the supplied output path and accepts the code option', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-preview-html-'.bin2hex(random_bytes(4));
    $workingFixture = $workingRoot.'/html-target';
    $outputPath = $workingRoot.'/nested/review.html';

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $result = run_prompt_weaver_preview([
            'preview',
            '--code=html-target',
            '--fixtures-root='.$workingRoot,
            '--output='.$outputPath,
        ]);

        expect($result['exitCode'])->toBe(0)
            ->and($result['stderr'])->toBe('')
            ->and($result['stdout'])->toContain('Created '.$outputPath)
            ->and(is_file($outputPath))->toBeTrue()
            ->and(file_get_contents($outputPath))
            ->toContain('<!doctype html>')
            ->toContain('data:image/png;base64,');
    } finally {
        remove_directory_preview($workingRoot);
    }
});

it('calibrates placeholder coordinates in config from the generated image', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-calibrate-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);
        $configPath = $workingFixture.'/raw.config.json';
        $configBeforeCalibration = file_get_contents($configPath);

        $result = run_prompt_weaver_preview([
            'calibrate',
            '--fixture='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Updated '.$workingFixture.'/config.json');

        $calibratedConfig = json_decode((string) file_get_contents($workingFixture.'/config.json'), true, 512, JSON_THROW_ON_ERROR);

        expect(file_get_contents($configPath))->toBe($configBeforeCalibration);
        expect($calibratedConfig['placeholders']['ssid'])->toHaveKey('box_y_pc');
        expect($calibratedConfig['placeholders']['password'])->toHaveKey('box_y_pc');
    } finally {
        remove_directory_preview($workingFixture);
    }
});

it('reports missing templates, missing preview inputs, and rendering failures with a non-zero exit code', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-preview-errors-'.bin2hex(random_bytes(4));
    mkdir($workingRoot, 0777, true);

    try {
        $missingTemplate = run_prompt_weaver_preview([
            'preview',
            'missing-template',
            '--fixtures-root='.$workingRoot,
        ]);

        expect($missingTemplate['exitCode'])->not->toBe(0)
            ->and($missingTemplate['stderr'])->toContain('Fixture directory not found');

        $missingConfigFixture = $workingRoot.'/missing-config';
        copy_directory_preview($sourceFixture, $missingConfigFixture);
        unlink($missingConfigFixture.'/config.json');
        unlink($missingConfigFixture.'/raw.config.json');

        $missingConfig = run_prompt_weaver_preview([
            'preview',
            '--fixture='.$missingConfigFixture,
        ]);

        expect($missingConfig['exitCode'])->not->toBe(0)
            ->and($missingConfig['stderr'])->toContain('Config file not found');
        remove_directory_preview($missingConfigFixture);

        $missingImageFixture = $workingRoot.'/missing-image';
        copy_directory_preview($sourceFixture, $missingImageFixture);
        unlink($missingImageFixture.'/image.png');

        $missingImage = run_prompt_weaver_preview([
            'preview',
            '--fixture='.$missingImageFixture,
        ]);

        expect($missingImage['exitCode'])->not->toBe(0)
            ->and($missingImage['stderr'])->toContain('Background image not found');
        remove_directory_preview($missingImageFixture);

        $invalidImageFixture = $workingRoot.'/invalid-image';
        copy_directory_preview($sourceFixture, $invalidImageFixture);
        file_put_contents($invalidImageFixture.'/image.png', 'not an image');

        $renderFailure = run_prompt_weaver_preview([
            'preview',
            '--fixture='.$invalidImageFixture,
        ]);

        expect($renderFailure['exitCode'])->not->toBe(0)
            ->and($renderFailure['stderr'])->toContain('Unable to load background image');
    } finally {
        remove_directory_preview($workingRoot);
    }
});

it('calibrates the qr position and width from the generated image', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-calibrate-qr-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $configPath = $workingFixture.'/raw.config.json';
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $config['placeholders']['qr']['x_pc'] = 10;
        $config['placeholders']['qr']['y_pc'] = 80;
        $config['placeholders']['qr']['width_pc'] = 10;
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT).PHP_EOL);

        $result = run_prompt_weaver_preview([
            'calibrate',
            '--fixture='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);

        $rawConfig = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $calibratedConfigPath = $workingFixture.'/config.json';
        $calibratedConfig = json_decode((string) file_get_contents($calibratedConfigPath), true, 512, JSON_THROW_ON_ERROR);

        expect($rawConfig['placeholders']['qr']['x_pc'])->toBe(10);
        expect($rawConfig['placeholders']['qr']['y_pc'])->toBe(80);
        expect($rawConfig['placeholders']['qr']['width_pc'])->toBe(10);
        expect($calibratedConfig['placeholders']['qr']['x_pc'])->toBeGreaterThan(45.0);
        expect($calibratedConfig['placeholders']['qr']['y_pc'])->toBeBetween(75.0, 82.0);
        expect($calibratedConfig['placeholders']['qr']['width_pc'])->toBeGreaterThan(25.0);
    } finally {
        remove_directory_preview($workingFixture);
    }
});

it('calibrates a template selected by code under a custom fixtures root', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-calibrate-root-'.bin2hex(random_bytes(4));
    $workingFixture = $workingRoot.'/calibration-target';

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $result = run_prompt_weaver_preview([
            'calibrate',
            'calibration-target',
            '--fixtures-root='.$workingRoot,
        ]);

        expect($result['exitCode'])->toBe(0)
            ->and($result['stderr'])->toBe('')
            ->and($result['stdout'])->toContain('Updated '.$workingFixture.'/config.json');

        $config = json_decode((string) file_get_contents($workingFixture.'/config.json'), true, 512, JSON_THROW_ON_ERROR);

        expect($config['placeholders']['ssid']['box_y_pc'])->toBeNumeric()
            ->and($config['placeholders']['password']['box_y_pc'])->toBeNumeric()
            ->and($config['placeholders']['qr']['x_pc'])->toBeNumeric()
            ->and($config['placeholders']['qr']['y_pc'])->toBeNumeric()
            ->and($config['placeholders']['qr']['width_pc'])->toBeNumeric();
    } finally {
        remove_directory_preview($workingRoot);
    }
});

it('reports missing working templates and required calibration inputs with a non-zero exit code', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingRoot = sys_get_temp_dir().'/prompt-weaver-calibrate-errors-'.bin2hex(random_bytes(4));
    mkdir($workingRoot, 0777, true);

    try {
        $missingTemplate = run_prompt_weaver_preview([
            'calibrate',
            'missing-template',
            '--fixtures-root='.$workingRoot,
        ]);

        expect($missingTemplate['exitCode'])->not->toBe(0)
            ->and($missingTemplate['stderr'])->toContain('Fixture directory not found');

        foreach (['raw.config.json', 'image.png'] as $missingInput) {
            $workingFixture = $workingRoot.'/missing-'.str_replace('.', '-', $missingInput);
            copy_directory_preview($sourceFixture, $workingFixture);
            unlink($workingFixture.'/'.$missingInput);

            $result = run_prompt_weaver_preview([
                'calibrate',
                '--fixture='.$workingFixture,
            ]);

            expect($result['exitCode'])->not->toBe(0)
                ->and($result['stderr'])->toContain(
                    $missingInput === 'raw.config.json' ? 'Raw config file not found' : 'Background image not found'
                );

            remove_directory_preview($workingFixture);
        }
    } finally {
        remove_directory_preview($workingRoot);
    }
});

it('reports calibration failure when the supplied image has no detectable placeholders', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-calibrate-failure-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);
        $configBeforeCalibration = file_get_contents($workingFixture.'/config.json');
        [$width, $height] = getimagesize($workingFixture.'/image.png');
        $blank = imagecreatetruecolor($width, $height);
        $black = imagecolorallocate($blank, 0, 0, 0);
        imagefill($blank, 0, 0, $black);
        imagepng($blank, $workingFixture.'/image.png');
        imagedestroy($blank);

        $result = run_prompt_weaver_preview([
            'calibrate',
            '--fixture='.$workingFixture,
        ]);

        expect($result['exitCode'])->not->toBe(0)
            ->and($result['stderr'])->toContain('Unable to detect the QR frame')
            ->and(file_get_contents($workingFixture.'/config.json'))->toBe($configBeforeCalibration);
    } finally {
        remove_directory_preview($workingFixture);
    }
});
