<?php

use Cable8mm\PromptWeaver\Services\WifiNotePublishService;

function remove_wifi_note_test_directory(string $directory): void
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

it('saves WifiNote credentials with private file permissions', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-'.bin2hex(random_bytes(4));
    $configPath = $root.'/.config/prompt-weaver/config.json';
    $service = new WifiNotePublishService(configPath: $configPath);

    try {
        $service->saveCredentials('https://wifinote.net/', 'secret-token');

        expect($service->credentials())->toBe([
            'server' => 'https://wifinote.net',
            'token' => 'secret-token',
        ]);
        expect(fileperms($configPath) & 0777)->toBe(0600);
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('rejects invalid credentials and empty template directories', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-'.bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    $service = new WifiNotePublishService(configPath: $root.'/config.json');

    try {
        expect(fn () => $service->saveCredentials('ftp://wifinote.net', 'token'))
            ->toThrow(RuntimeException::class, 'valid HTTP or HTTPS URL');
        expect(fn () => $service->saveCredentials('https://wifinote.net', ''))
            ->toThrow(RuntimeException::class, 'must not be empty');
        expect(fn () => $service->createTemplatePack($root))
            ->toThrow(RuntimeException::class, 'No template directories found');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('reports failures to persist WifiNote credentials', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-'.bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root.'/not-a-directory', 'file');
    $service = new WifiNotePublishService(configPath: $root.'/not-a-directory/config.json');

    try {
        expect(fn () => $service->saveCredentials('https://wifinote.net', 'secret-token'))
            ->toThrow(RuntimeException::class, 'Unable to create config directory');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('creates a ZIP with all templates and a root pack manifest', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-'.bin2hex(random_bytes(4));
    $dist = $root.'/dist';
    mkdir($dist.'/cafe-minimal/nested', 0777, true);
    mkdir($dist.'/winter-forest', 0777, true);
    file_put_contents($dist.'/cafe-minimal/nested/image.png', 'image');
    file_put_contents($dist.'/winter-forest/config.json', '{"name":"winter"}');
    $service = new WifiNotePublishService(configPath: $root.'/config.json');
    $archivePath = null;

    try {
        $archivePath = $service->createTemplatePack($dist);
        expect(basename($archivePath))->toMatch('/^template-pack-\d{8}-\d{6}(?:-\d+)?\.zip$/');

        $archive = new ZipArchive;
        expect($archive->open($archivePath))->toBeTrue();
        expect($archive->locateName('manifest.json'))->not->toBeFalse();
        expect($archive->locateName('cafe-minimal/nested/image.png'))->not->toBeFalse();
        expect($archive->locateName('winter-forest/config.json'))->not->toBeFalse();

        $manifest = json_decode((string) $archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($manifest)->toHaveKeys(['schema', 'generator', 'generator_version', 'created_at']);
        expect($manifest['generator'])->toBe('prompt-weaver');
        expect($manifest['created_at'])->toBeString();
        $archive->close();
    } finally {
        if (is_string($archivePath)) {
            $service->removeTemplatePack($archivePath);
        }

        remove_wifi_note_test_directory($root);
    }
});
