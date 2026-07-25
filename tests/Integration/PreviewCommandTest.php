<?php

function run_prompt_weaver_preview(array $args, ?string $cwd = null): array
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
    $sourceFixture = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-preview-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

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

it('calibrates placeholder coordinates in config from the generated image', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-calibrate-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $result = run_prompt_weaver_preview([
            'calibrate',
            '--fixture='.$workingFixture,
        ]);

        expect($result['exitCode'])->toBe(0);
        expect($result['stderr'])->toBe('');
        expect($result['stdout'])->toContain('Updated '.$workingFixture.'/config.json');

        $config = json_decode((string) file_get_contents($workingFixture.'/config.json'), true, 512, JSON_THROW_ON_ERROR);

        expect($config['placeholders']['ssid']['box_y_pc'])->toBeGreaterThan(40.0);
        expect($config['placeholders']['password']['box_y_pc'])->toBeGreaterThan(52.0);
    } finally {
        remove_directory_preview($workingFixture);
    }
});

it('calibrates the qr position and width from the generated image', function () {
    $sourceFixture = dirname(__DIR__).'/Fixtures/chatgpt/cafe-restaurant';
    $workingFixture = sys_get_temp_dir().'/prompt-weaver-calibrate-qr-'.bin2hex(random_bytes(4));

    try {
        copy_directory_preview($sourceFixture, $workingFixture);

        $configPath = $workingFixture.'/config.json';
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

        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);

        expect($config['placeholders']['qr']['x_pc'])->toBeGreaterThan(45.0);
        expect($config['placeholders']['qr']['y_pc'])->toBeBetween(75.0, 82.0);
        expect($config['placeholders']['qr']['width_pc'])->toBeGreaterThan(25.0);
    } finally {
        remove_directory_preview($workingFixture);
    }
});
