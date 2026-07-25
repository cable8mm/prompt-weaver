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
    } finally {
        remove_directory_preview($workingFixture);
    }
});
