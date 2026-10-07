<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Services;

use Cable8mm\PromptWeaver\Console\Application;
use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

final class WifiNotePublishService
{
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
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
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

            chmod($temporaryPath, 0600);
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

        $timestamp = (new DateTimeImmutable)->format('Ymd-His');
        $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'template-pack-'.$timestamp.'.zip';
        $suffix = 1;
        while (file_exists($archivePath)) {
            $archivePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'template-pack-'.$timestamp.'-'.$suffix.'.zip';
            $suffix++;
        }

        $archive = new ZipArchive;
        $openResult = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL);
        if ($openResult !== true) {
            throw new RuntimeException("Unable to create template pack: {$archivePath}");
        }

        try {
            foreach ($templateDirectories as $templateDirectory) {
                $rootPath = rtrim($distDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$templateDirectory;
                if (! $archive->addEmptyDir($templateDirectory)) {
                    throw new RuntimeException("Unable to add directory to template pack: {$templateDirectory}");
                }
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($rootPath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST,
                );

                foreach ($iterator as $item) {
                    if ($item->isLink()) {
                        continue;
                    }

                    $relativePath = str_replace(
                        DIRECTORY_SEPARATOR,
                        '/',
                        substr($item->getPathname(), strlen(rtrim($distDirectory, DIRECTORY_SEPARATOR)) + 1),
                    );

                    if ($item->isDir()) {
                        if (! $archive->addEmptyDir($relativePath)) {
                            throw new RuntimeException("Unable to add directory to template pack: {$relativePath}");
                        }
                    } elseif (! $archive->addFile($item->getPathname(), $relativePath)) {
                        throw new RuntimeException("Unable to add file to template pack: {$relativePath}");
                    }
                }
            }

            $manifest = [
                'schema' => 1,
                'generator' => 'prompt-weaver',
                'generator_version' => Application::VERSION,
                'created_at' => (new DateTimeImmutable)->format(DATE_ATOM),
            ];
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

        return $archivePath;
    }

    public function upload(string $archivePath): void
    {
        $credentials = $this->credentials();
        $this->uploadClient->upload($credentials['server'], $credentials['token'], $archivePath);
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
