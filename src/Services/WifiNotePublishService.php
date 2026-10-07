<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Services;

use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

final class WifiNotePublishService
{
    private const int MAX_TEMPLATES = 50;

    private const int MAX_TEMPLATE_PACK_BYTES = 104857600;

    private readonly string $configPath;

    public function __construct(
        private readonly WifiNoteUploadClient $uploadClient = new WifiNoteUploadClient,
        ?string $configPath = null,
    ) {
        $home = getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir();
        $this->configPath = $configPath ?? rtrim($home, DIRECTORY_SEPARATOR).'/.config/prompt-weaver/config.json';
    }

    public function saveCredentials(string $server, string $token): void
    {
        $server = rtrim(trim($server), '/');
        $token = trim($token);
        $this->validateServer($server);

        if ($token === '') {
            throw new RuntimeException('Personal Access Token must not be empty.');
        }

        $directory = dirname($this->configPath);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create config directory: {$directory}");
        }

        $temporaryPath = tempnam($directory, '.config-');
        if ($temporaryPath === false) {
            throw new RuntimeException("Unable to write config file: {$this->configPath}");
        }

        try {
            $json = json_encode([
                'server' => $server,
                'token' => $token,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

            if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write config file: {$this->configPath}");
            }

            if (! chmod($temporaryPath, 0600)) {
                throw new RuntimeException("Unable to set private permissions on config file: {$this->configPath}");
            }

            if (! rename($temporaryPath, $this->configPath)) {
                throw new RuntimeException("Unable to save config file: {$this->configPath}");
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @return array{server: string, token: string} */
    public function credentials(): array
    {
        if (! is_file($this->configPath)) {
            throw new RuntimeException('WifiNote is not configured. Run "prompt-weaver login" first.');
        }

        try {
            $config = json_decode((string) file_get_contents($this->configPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException("WifiNote config is not valid JSON: {$this->configPath}", previous: $exception);
        }

        if (! is_array($config) || ! is_string($config['server'] ?? null) || ! is_string($config['token'] ?? null)) {
            throw new RuntimeException("WifiNote config must contain a server URL and token: {$this->configPath}");
        }

        $server = rtrim(trim($config['server']), '/');
        $token = trim($config['token']);
        $this->validateServer($server);

        if ($token === '') {
            throw new RuntimeException("WifiNote config contains an empty token: {$this->configPath}");
        }

        return ['server' => $server, 'token' => $token];
    }

    public function createTemplatePack(string $distDirectory): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to create a template pack.');
        }

        if (! is_dir($distDirectory)) {
            throw new RuntimeException("Template directory not found: {$distDirectory}");
        }

        $templateDirectories = [];
        foreach (scandir($distDirectory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = rtrim($distDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$entry;
            if (is_dir($path) && ! is_link($path)) {
                $templateDirectories[] = $entry;
            }
        }

        sort($templateDirectories, SORT_STRING);
        if ($templateDirectories === []) {
            throw new RuntimeException("No template directories found: {$distDirectory}");
        }

        if (count($templateDirectories) > self::MAX_TEMPLATES) {
            throw new RuntimeException('WifiNote accepts at most 50 templates per Template Pack.');
        }

        foreach ($templateDirectories as $templateDirectory) {
            if (preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $templateDirectory) !== 1) {
                throw new RuntimeException("Template code is not valid for WifiNote: {$templateDirectory}");
            }
        }

        $timestamp = (new DateTimeImmutable)->format('Ymd-His');
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'template-pack-'.$timestamp.'.zip';

        if (file_exists($archivePath)) {
            throw new RuntimeException("Template pack already exists for this second: {$archivePath}");
        }

        $archive = new ZipArchive;
        $openResult = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL);
        if ($openResult !== true) {
            throw new RuntimeException("Unable to create template pack: {$archivePath}");
        }

        try {
            if (! $archive->addEmptyDir('templates')) {
                throw new RuntimeException('Unable to add templates directory to template pack.');
            }

            foreach ($templateDirectories as $templateDirectory) {
                $rootPath = rtrim($distDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$templateDirectory;
                $configPath = $rootPath.'/config.json';
                if (! is_file($configPath) || ! is_readable($configPath)) {
                    throw new RuntimeException("Required WifiNote template file is missing or unreadable: {$configPath}");
                }

                try {
                    $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $exception) {
                    throw new RuntimeException("Template config is not valid JSON: {$configPath}", previous: $exception);
                }

                if (! is_array($config) || ($config['metadata']['code'] ?? null) !== $templateDirectory) {
                    throw new RuntimeException("Template config metadata.code must match its directory: {$configPath}");
                }

                $templateArchivePath = 'templates/'.$templateDirectory;
                if (! $archive->addEmptyDir($templateArchivePath)) {
                    throw new RuntimeException("Unable to add directory to template pack: {$templateArchivePath}");
                }

                foreach (['config.json', 'image.png', 'preview.png'] as $filename) {
                    $sourcePath = $rootPath.'/'.$filename;
                    $entryPath = $templateArchivePath.'/'.$filename;

                    if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
                        throw new RuntimeException("Required WifiNote template file is missing or unreadable: {$sourcePath}");
                    }

                    if (! $archive->addFile($sourcePath, $entryPath)) {
                        throw new RuntimeException("Unable to add file to template pack: {$entryPath}");
                    }
                }
            }

            $manifest = ['templates' => $templateDirectories];
            $manifestJson = json_encode(
                $manifest,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ).PHP_EOL;

            if (! $archive->addFromString('manifest.json', $manifestJson) || ! $archive->close()) {
                throw new RuntimeException("Unable to finalize template pack: {$archivePath}");
            }
        } catch (\Throwable $throwable) {
            $archive->close();
            if (is_file($archivePath)) {
                unlink($archivePath);
            }

            throw $throwable;
        }

        $archiveSize = filesize($archivePath);
        if ($archiveSize === false || $archiveSize > self::MAX_TEMPLATE_PACK_BYTES) {
            if (! unlink($archivePath)) {
                throw new RuntimeException("Template pack exceeds WifiNote's 100 MiB limit and could not be removed: {$archivePath}");
            }

            throw new RuntimeException("Template pack exceeds WifiNote's 100 MiB upload limit: {$archivePath}");
        }

        return $archivePath;
    }

    /** @return array{status_code: 202, upload_id: string} */
    public function upload(string $archivePath): array
    {
        $credentials = $this->credentials();

        return $this->uploadClient->upload($credentials['server'], $credentials['token'], $archivePath);
    }

    public function removeTemplatePack(string $archivePath): void
    {
        if (is_file($archivePath) && ! unlink($archivePath)) {
            throw new RuntimeException("Unable to remove temporary template pack: {$archivePath}");
        }
    }

    private function validateServer(string $server): void
    {
        $parts = parse_url($server);
        if ($server === '' || ! is_array($parts) || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('WifiNote Server URL must be a valid HTTP or HTTPS URL.');
        }
    }
}
