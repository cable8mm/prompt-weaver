<?php

use Cable8mm\PromptWeaver\Services\WifiNotePublishService;

function remove_wifi_note_publish_test_directory(string $directory): void
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

function run_wifi_note_publish_command(array $arguments, string $home, string $input, ?string $cwd = null): array
{
    $command = array_merge([PHP_BINARY, dirname(__DIR__, 2).'/bin/prompt-weaver'], $arguments);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $cwd ?? dirname(__DIR__, 2), array_merge($_ENV, ['HOME' => $home]));

    expect($process)->not->toBeFalse();
    fwrite($pipes[0], $input);
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

it('does not upload a template pack unless the user approves', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    $dist = $root.'/dist';
    mkdir($dist.'/sample-template', 0777, true);
    mkdir($dist.'/second-template', 0777, true);
    file_put_contents($dist.'/sample-template/preview.png', 'reviewed preview');
    file_put_contents($dist.'/sample-template/manifest.json', '{"code":"sample-template"}');
    file_put_contents($dist.'/second-template/preview.png', 'second preview');
    file_put_contents($dist.'/second-template/manifest.json', '{"code":"second-template"}');

    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('https://wifinote.net', 'secret-token');

    try {
        $result = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$dist,
        ], $home, "n\n");

        expect($result['exitCode'])->toBe(0);
        expect($result['stdout'])->toContain('Publish cancelled.');
        expect($result['stdout'])->not->toContain('Upload completed successfully.');
        expect($result['stderr'])->toContain('Publish template pack to WifiNote? [y/N]');
        expect(file_get_contents($dist.'/sample-template/preview.png'))->toBe('reviewed preview');
        expect(file_get_contents($dist.'/sample-template/manifest.json'))->toBe('{"code":"sample-template"}');
        expect(file_get_contents($dist.'/second-template/preview.png'))->toBe('second preview');
        expect(file_get_contents($dist.'/second-template/manifest.json'))->toBe('{"code":"second-template"}');
        expect(glob($dist.'/*/approval.json'))->toBe([]);
    } finally {
        remove_wifi_note_publish_test_directory($root);
    }
});

it('uses dist as the default publication root', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-default-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    mkdir($root.'/dist/sample-template', 0777, true);
    file_put_contents($root.'/dist/sample-template/manifest.json', '{"code":"sample-template"}');
    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('https://wifinote.net', 'secret-token');

    try {
        $result = run_wifi_note_publish_command(['publish'], $home, "n\n", $root);

        expect($result['exitCode'])->toBe(0)
            ->and($result['stdout'])->toContain('Publish cancelled.')
            ->and($result['stderr'])->toContain('Publish template pack to WifiNote? [y/N]');
    } finally {
        remove_wifi_note_publish_test_directory($root);
    }
});

it('reports missing and empty publication roots before requesting confirmation', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-errors-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    mkdir($root, 0777, true);
    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('https://wifinote.net', 'secret-token');

    try {
        $missingDefaultRoot = run_wifi_note_publish_command(['publish'], $home, '', $root);
        expect($missingDefaultRoot['exitCode'])->not->toBe(0)
            ->and($missingDefaultRoot['stderr'])->toContain('Template directory not found: dist');

        $missingSelectedRoot = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$root.'/missing',
        ], $home, '', $root);
        expect($missingSelectedRoot['exitCode'])->not->toBe(0)
            ->and($missingSelectedRoot['stderr'])->toContain('Template directory not found: '.$root.'/missing');

        mkdir($root.'/empty', 0777, true);
        $emptySelectedRoot = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$root.'/empty',
        ], $home, '', $root);
        expect($emptySelectedRoot['exitCode'])->not->toBe(0)
            ->and($emptySelectedRoot['stderr'])->toContain('No template directories found');
    } finally {
        remove_wifi_note_publish_test_directory($root);
    }
});
