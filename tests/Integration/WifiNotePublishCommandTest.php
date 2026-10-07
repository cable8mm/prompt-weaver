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
    file_put_contents($dist.'/sample-template/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/sample-template/image.png', 'sample image');
    file_put_contents($dist.'/sample-template/preview.png', 'reviewed preview');
    file_put_contents($dist.'/sample-template/manifest.json', '{"code":"sample-template"}');
    file_put_contents($dist.'/second-template/config.json', '{"metadata":{"code":"second-template"}}');
    file_put_contents($dist.'/second-template/image.png', 'second image');
    file_put_contents($dist.'/second-template/preview.png', 'second preview');
    file_put_contents($dist.'/second-template/manifest.json', '{"code":"second-template"}');

    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('http://127.0.0.1:0', 'secret-token');

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

it('defaults to declining publication when confirmation input is empty', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-default-decline-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    $dist = $root.'/dist/sample-template';
    mkdir($dist, 0777, true);
    file_put_contents($dist.'/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/image.png', 'sample image');
    file_put_contents($dist.'/preview.png', 'sample preview');
    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('http://127.0.0.1:0', 'secret-token');

    try {
        $result = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$root.'/dist',
        ], $home, "\n");

        expect($result['exitCode'])->toBe(0)
            ->and($result['stdout'])->toContain('Publish cancelled.');
    } finally {
        remove_wifi_note_publish_test_directory($root);
    }
});

it('fails before confirmation when WifiNote credentials are missing', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-no-credentials-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    $dist = $root.'/dist/sample-template';
    mkdir($dist, 0777, true);
    file_put_contents($dist.'/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/image.png', 'sample image');
    file_put_contents($dist.'/preview.png', 'sample preview');

    try {
        $result = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$root.'/dist',
        ], $home, "y\n");

        expect($result['exitCode'])->not->toBe(0)
            ->and($result['stderr'])->toContain('WifiNote is not configured')
            ->and($result['stderr'])->not->toContain('Publish template pack to WifiNote?');
    } finally {
        remove_wifi_note_publish_test_directory($root);
    }
});

it('exits non-zero when WifiNote rejects an approved upload', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-rejected-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    $dist = $root.'/dist/sample-template';
    mkdir($dist, 0777, true);
    file_put_contents($dist.'/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/image.png', 'sample image');
    file_put_contents($dist.'/preview.png', 'sample preview');

    $routerPath = $root.'/router.php';
    file_put_contents($routerPath, '<?php http_response_code(401); echo "{}";');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);
    $server = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", $routerPath],
        [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ],
        $pipes,
    );
    expect($server)->not->toBeFalse();

    try {
        $ready = false;
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.05);
            if (is_resource($connection)) {
                fclose($connection);
                $ready = true;
                break;
            }

            usleep(20_000);
        }

        expect($ready)->toBeTrue();
        (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
            ->saveCredentials("http://127.0.0.1:{$port}", 'secret-token');
        $result = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$root.'/dist',
        ], $home, "y\n");

        expect($result['exitCode'])->not->toBe(0)
            ->and($result['stderr'])->toContain('rejected the Personal Access Token');
    } finally {
        if (is_resource($server)) {
            proc_terminate($server);
            proc_close($server);
        }

        remove_wifi_note_publish_test_directory($root);
    }
});

it('uses dist as the default publication root', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-default-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    mkdir($root.'/dist/sample-template', 0777, true);
    file_put_contents($root.'/dist/sample-template/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($root.'/dist/sample-template/image.png', 'sample image');
    file_put_contents($root.'/dist/sample-template/preview.png', 'sample preview');
    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('http://127.0.0.1:0', 'secret-token');

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
        ->saveCredentials('http://127.0.0.1:0', 'secret-token');

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
