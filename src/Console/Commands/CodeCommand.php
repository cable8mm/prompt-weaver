<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CodeCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('code')->setDescription('Rename a fixture using config.json style.theme.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Current fixture code.');
        $this->addFixturesRootOption();
        $this->addOption('dist-root', null, InputOption::VALUE_REQUIRED, 'Export root directory.', 'dist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $oldCode = $this->validatePathSegment((string) $input->getArgument('fixture'), 'code');
        $fixturesRoot = $this->fixturesRoot($input);
        $sourceDirectory = $this->fixtureDirectoryFromReference($oldCode, $fixturesRoot);
        $configPath = $sourceDirectory.'/config.json';

        if (! is_dir($sourceDirectory)) {
            throw new RuntimeException("Fixture directory not found: {$sourceDirectory}");
        }

        $config = $this->readJsonFile($configPath);
        $theme = $config['style']['theme'] ?? null;
        if (! is_string($theme) || trim($theme) === '') {
            throw new RuntimeException("Config field 'style.theme' is missing or invalid: {$configPath}");
        }

        $newCode = $this->slugify($theme);
        if ($newCode === '') {
            throw new RuntimeException("Unable to derive a code from style.theme: {$theme}");
        }

        if ($newCode === $oldCode) {
            $this->displayUpdated($sourceDirectory);

            return self::SUCCESS;
        }

        $targetDirectory = rtrim($fixturesRoot, '/').'/'.$newCode;
        $distRoot = (string) $input->getOption('dist-root');
        $distSourceDirectory = rtrim($distRoot, '/').'/'.$oldCode;
        $distTargetDirectory = rtrim($distRoot, '/').'/'.$newCode;

        if (is_dir($targetDirectory)) {
            throw new RuntimeException("Fixture already exists: {$targetDirectory}");
        }

        if (is_dir($distTargetDirectory)) {
            throw new RuntimeException("Export directory already exists: {$distTargetDirectory}");
        }

        $manifestPath = $sourceDirectory.'/manifest.json';
        $manifest = $this->readJsonFile($manifestPath);
        $manifest['code'] = $newCode;
        if (isset($config['metadata']) && is_array($config['metadata'])) {
            $config['metadata']['code'] = $newCode;
        }

        $this->writeJson($manifestPath, $manifest);
        $this->writeJson($configPath, $config);

        if (! rename($sourceDirectory, $targetDirectory)) {
            throw new RuntimeException("Unable to rename fixture: {$sourceDirectory} -> {$targetDirectory}");
        }

        if (is_dir($distSourceDirectory) && ! rename($distSourceDirectory, $distTargetDirectory)) {
            throw new RuntimeException("Unable to rename export: {$distSourceDirectory} -> {$distTargetDirectory}");
        }

        $distConfigPath = $distTargetDirectory.'/config.json';
        if (is_file($distConfigPath)) {
            $distConfig = $this->readJsonFile($distConfigPath);
            $distConfig['metadata']['code'] = $newCode;
            $this->writeJson($distConfigPath, $distConfig);
        }

        $this->displayUpdated("{$sourceDirectory} -> {$targetDirectory}");

        return self::SUCCESS;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

        return trim($value, '-');
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
