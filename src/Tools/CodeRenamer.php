<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Tools;

use RuntimeException;

final class CodeRenamer
{
    /**
     * Build a complete rename plan without changing anything on disk.
     *
     * @return array{
     *     oldCode: string,
     *     newCode: string,
     *     sourceDirectory: string,
     *     targetDirectory: string,
     *     distSourceDirectory: string,
     *     distTargetDirectory: string,
     *     manifest: array<string, mixed>,
     *     config: array<string, mixed>,
     *     distConfig: array<string, mixed>|null
     * }
     */
    public function plan(string $oldCode, string $fixturesRoot, string $distRoot): array
    {
        $sourceDirectory = rtrim($fixturesRoot, '/').'/'.$oldCode;
        if (! is_dir($sourceDirectory)) {
            throw new RuntimeException("Fixture directory not found: {$sourceDirectory}");
        }

        $configPath = $sourceDirectory.'/config.json';
        $config = $this->readJsonFile($configPath);
        $theme = $config['style']['theme'] ?? null;
        if (! is_string($theme) || trim($theme) === '') {
            throw new RuntimeException("Config field 'style.theme' is missing or invalid: {$configPath}");
        }

        $newCode = (new Code)->deriveFromTheme($theme);
        $targetDirectory = rtrim($fixturesRoot, '/').'/'.$newCode;
        $distSourceDirectory = rtrim($distRoot, '/').'/'.$oldCode;
        $distTargetDirectory = rtrim($distRoot, '/').'/'.$newCode;

        if ($newCode !== $oldCode) {
            if (is_dir($targetDirectory)) {
                throw new RuntimeException("Fixture already exists: {$targetDirectory}");
            }

            if (is_dir($distTargetDirectory)) {
                throw new RuntimeException("Export directory already exists: {$distTargetDirectory}");
            }
        }

        $manifest = $this->readJsonFile($sourceDirectory.'/manifest.json');
        if (isset($config['metadata']) && is_array($config['metadata'])) {
            $config['metadata']['code'] = $newCode;
        }

        $distConfig = null;
        $distConfigPath = $distSourceDirectory.'/config.json';
        if (is_file($distConfigPath)) {
            $distConfig = $this->readJsonFile($distConfigPath);
            $distConfig['metadata']['code'] = $newCode;
        }

        return [
            'oldCode' => $oldCode,
            'newCode' => $newCode,
            'sourceDirectory' => $sourceDirectory,
            'targetDirectory' => $targetDirectory,
            'distSourceDirectory' => $distSourceDirectory,
            'distTargetDirectory' => $distTargetDirectory,
            'manifest' => $manifest,
            'config' => $config,
            'distConfig' => $distConfig,
        ];
    }

    /**
     * Apply a previously validated plan.
     *
     * @param array{
     *     oldCode: string,
     *     newCode: string,
     *     sourceDirectory: string,
     *     targetDirectory: string,
     *     distSourceDirectory: string,
     *     distTargetDirectory: string,
     *     manifest: array<string, mixed>,
     *     config: array<string, mixed>,
     *     distConfig: array<string, mixed>|null
     * } $plan
     */
    public function apply(array $plan): string
    {
        if ($plan['newCode'] === $plan['oldCode']) {
            return $plan['sourceDirectory'];
        }

        $manifest = $plan['manifest'];
        $manifest['code'] = $plan['newCode'];

        $this->writeJson($plan['sourceDirectory'].'/manifest.json', $manifest);
        $this->writeJson($plan['sourceDirectory'].'/config.json', $plan['config']);

        if (! rename($plan['sourceDirectory'], $plan['targetDirectory'])) {
            throw new RuntimeException("Unable to rename fixture: {$plan['sourceDirectory']} -> {$plan['targetDirectory']}");
        }

        if (is_dir($plan['distSourceDirectory']) && ! rename($plan['distSourceDirectory'], $plan['distTargetDirectory'])) {
            throw new RuntimeException("Unable to rename export: {$plan['distSourceDirectory']} -> {$plan['distTargetDirectory']}");
        }

        if ($plan['distConfig'] !== null) {
            $this->writeJson($plan['distTargetDirectory'].'/config.json', $plan['distConfig']);
        }

        return $plan['sourceDirectory'].' -> '.$plan['targetDirectory'];
    }

    /** @return array<string, mixed> */
    private function readJsonFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("JSON file not found: {$path}");
        }

        $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($json)) {
            throw new RuntimeException("JSON object expected: {$path}");
        }

        return $json;
    }

    /** @param array<string, mixed> $json */
    private function writeJson(string $path, array $json): void
    {
        $contents = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Unable to write: {$path}");
        }
    }
}
