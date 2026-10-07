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
    foreach (['cafe-minimal', 'winter-forest'] as $code) {
        file_put_contents($dist.'/'.$code.'/config.json', json_encode(['metadata' => ['code' => $code]], JSON_THROW_ON_ERROR));
        file_put_contents($dist.'/'.$code.'/image.png', $code.' image');
        file_put_contents($dist.'/'.$code.'/preview.png', $code.' preview');
        file_put_contents($dist.'/'.$code.'/image.prompt', 'not included in WifiNote packs');
        file_put_contents($dist.'/'.$code.'/manifest.json', '{"code":"'.$code.'"}');
    }
    file_put_contents($dist.'/cafe-minimal/nested/ignored.txt', 'not included in WifiNote packs');
    $service = new WifiNotePublishService(configPath: $root.'/config.json');
    $archivePath = null;

    try {
        $archivePath = $service->createTemplatePack($dist);
        expect(basename($archivePath))->toMatch('/^template-pack-\d{8}-\d{6}\.zip$/');

        $archive = new ZipArchive;
        expect($archive->open($archivePath))->toBeTrue();
        $entries = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entries[] = $archive->getNameIndex($index);
        }
        sort($entries, SORT_STRING);
        expect($entries)->toBe([
            'manifest.json',
            'templates/',
            'templates/cafe-minimal/',
            'templates/cafe-minimal/config.json',
            'templates/cafe-minimal/image.png',
            'templates/cafe-minimal/preview.png',
            'templates/winter-forest/',
            'templates/winter-forest/config.json',
            'templates/winter-forest/image.png',
            'templates/winter-forest/preview.png',
        ]);

        $manifest = json_decode((string) $archive->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        expect($manifest)->toBe(['templates' => ['cafe-minimal', 'winter-forest']]);
        expect($archive->locateName('templates/cafe-minimal/image.prompt'))->toBeFalse();
        expect($archive->locateName('templates/cafe-minimal/manifest.json'))->toBeFalse();
        expect($archive->locateName('templates/cafe-minimal/nested/ignored.txt'))->toBeFalse();
        $archive->close();
    } finally {
        if (is_string($archivePath)) {
            $service->removeTemplatePack($archivePath);
        }

        remove_wifi_note_test_directory($root);
    }
});

it('rejects more templates than WifiNote can import in one pack', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-limit-'.bin2hex(random_bytes(4));
    $dist = $root.'/dist';
    mkdir($dist, 0777, true);

    for ($index = 1; $index <= 51; $index++) {
        mkdir(sprintf('%s/template-%02d', $dist, $index));
    }

    $service = new WifiNotePublishService(configPath: $root.'/config.json');

    try {
        expect(fn () => $service->createTemplatePack($dist))
            ->toThrow(RuntimeException::class, 'at most 50 templates');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('rejects a ZIP larger than WifiNote upload limit', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-size-'.bin2hex(random_bytes(4));
    $dist = $root.'/dist';
    mkdir($dist.'/sample-template', 0777, true);
    file_put_contents($dist.'/sample-template/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/sample-template/preview.png', 'preview');
    try {
        $image = fopen($dist.'/sample-template/image.png', 'wb');
        if (! is_resource($image)) {
            throw new RuntimeException('Unable to create oversized image fixture.');
        }

        for ($chunk = 0; $chunk < 101; $chunk++) {
            $content = random_bytes(1024 * 1024);
            if (fwrite($image, $content) !== strlen($content)) {
                throw new RuntimeException('Unable to write oversized image fixture.');
            }
        }
        fclose($image);

        $service = new WifiNotePublishService(configPath: $root.'/config.json');
        expect(fn () => $service->createTemplatePack($dist))
            ->toThrow(RuntimeException::class, '100 MiB upload limit');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('rejects templates without the exact files required by WifiNote', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-files-'.bin2hex(random_bytes(4));
    $dist = $root.'/dist';
    mkdir($dist.'/sample-template', 0777, true);
    file_put_contents($dist.'/sample-template/config.json', '{"metadata":{"code":"sample-template"}}');
    file_put_contents($dist.'/sample-template/image.png', 'image');
    $service = new WifiNotePublishService(configPath: $root.'/config.json');

    try {
        expect(fn () => $service->createTemplatePack($dist))
            ->toThrow(RuntimeException::class, 'preview.png');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});

it('rejects a template whose config code does not match its directory', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-wifinote-code-'.bin2hex(random_bytes(4));
    $dist = $root.'/dist';
    mkdir($dist.'/sample-template', 0777, true);
    file_put_contents($dist.'/sample-template/config.json', '{"metadata":{"code":"other-template"}}');
    file_put_contents($dist.'/sample-template/image.png', 'image');
    file_put_contents($dist.'/sample-template/preview.png', 'preview');
    $service = new WifiNotePublishService(configPath: $root.'/config.json');

    try {
        expect(fn () => $service->createTemplatePack($dist))
            ->toThrow(RuntimeException::class, 'metadata.code must match');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});
