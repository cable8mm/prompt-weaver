<?php

use Cable8mm\PromptWeaver\Services\WifiNoteUploadClient;

function remove_wifi_note_upload_test_directory(string $directory): void
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

it('maps WifiNote upload status, network, and timeout failures to clear messages', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-upload-'.bin2hex(random_bytes(4));
    mkdir($root, 0700, true);
    $modePath = $root.'/mode';
    $requestPath = $root.'/request.json';
    $archivePath = $root.'/pack.zip';
    file_put_contents($archivePath, 'template pack');
    file_put_contents($modePath, '200');

    $routerPath = $root.'/router.php';
    file_put_contents($routerPath, sprintf(<<<'PHP'
<?php
$mode = trim((string) file_get_contents(%s));
file_put_contents(%s, json_encode([
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'upload' => $_FILES['file']['name'] ?? null,
]), LOCK_EX);
if ($mode === 'timeout') {
    sleep(3);
}
http_response_code($mode === 'timeout' ? 200 : (int) $mode);
echo '{}';
PHP, var_export($modePath, true), var_export($requestPath, true)));

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr((string) $address, ':'), 1);

    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", $routerPath],
        [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'a'],
            2 => ['file', '/dev/null', 'a'],
        ],
        $pipes,
    );
    expect($process)->not->toBeFalse();

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
        $client = new WifiNoteUploadClient(connectTimeoutSeconds: 1, timeoutSeconds: 1);
        $server = "http://127.0.0.1:{$port}";

        foreach (['408', '504'] as $status) {
            file_put_contents($modePath, $status);
            expect(fn () => $client->upload($server, 'secret-token', $archivePath))
                ->toThrow(RuntimeException::class, 'timed out');
        }

        foreach ([
            '401' => 'rejected the Personal Access Token',
            '403' => 'does not have permission',
            '422' => 'could not accept this template pack',
            '500' => 'server error',
        ] as $status => $message) {
            file_put_contents($modePath, $status);
            expect(fn () => $client->upload($server, 'secret-token', $archivePath))
                ->toThrow(RuntimeException::class, $message);
        }

        file_put_contents($modePath, '200');
        $client->upload($server, 'secret-token', $archivePath);
        $request = json_decode((string) file_get_contents($requestPath), true, 512, JSON_THROW_ON_ERROR);
        expect($request)->toBe([
            'authorization' => 'Bearer secret-token',
            'upload' => basename($archivePath),
        ]);

        file_put_contents($modePath, 'timeout');
        expect(fn () => $client->upload($server, 'secret-token', $archivePath))
            ->toThrow(RuntimeException::class, 'timed out');

        proc_terminate($process);
        proc_close($process);
        $process = false;

        expect(fn () => $client->upload($server, 'secret-token', $archivePath))
            ->toThrow(RuntimeException::class, 'Network error');
    } finally {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }

        remove_wifi_note_upload_test_directory($root);
    }
});
