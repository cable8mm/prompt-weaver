<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('export')->setDescription('Package a generated image and fixture config for Laravel import.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Template code.');
        $this->addOption('image', null, InputOption::VALUE_REQUIRED, 'Generated image path. Uses fixture/image.png when omitted.');
        $this->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Output directory.', null);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = (string) $input->getArgument('fixture');
        $fixturesRoot = $this->fixturesRoot($input);
        $outputDirectory = $input->getOption('output-dir')
            ?? 'dist/'.$this->validatePathSegment($code, 'code');
        $imagePath = $input->getOption('image');

        $this->exportFixture($code, $fixturesRoot, is_string($imagePath) ? $imagePath : null, $outputDirectory);

        return self::SUCCESS;
    }

    protected function exportFixture(string $code, string $fixturesRoot, ?string $imagePath, string $outputDirectory): void
    {
        $fixtureDirectory = $this->fixtureDirectoryFromReference($code, $fixturesRoot);
        $manifestPath = $fixtureDirectory.'/manifest.json';
        $designBriefPath = $fixtureDirectory.'/design-brief.json';
        $configPath = $fixtureDirectory.'/config.json';
        $previewPath = $fixtureDirectory.'/preview.png';
        $imagePath ??= $fixtureDirectory.'/image.png';

        if (! is_file($manifestPath)) {
            throw new RuntimeException("Manifest file not found: {$manifestPath}");
        }

        if (! is_file($designBriefPath)) {
            throw new RuntimeException("Design brief file not found: {$designBriefPath}");
        }

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }

        if (! is_string($imagePath) || ! is_file($imagePath)) {
            throw new RuntimeException("Image file not found: {$imagePath}");
        }

        $manifest = $this->readJsonFile($manifestPath);
        $designBrief = $this->readJsonFile($designBriefPath);
        $config = $this->readJsonFile($configPath);
        unset($config['schema_version'], $config['metadata']);
        $config = [
            'schema_version' => 1,
            'metadata' => $this->metadata($manifest, $designBrief, $manifestPath, $designBriefPath),
        ] + $config;
        $this->validateConfig($config, $configPath);
        $this->validateImage($imagePath, $config);

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException("Unable to create output directory: {$outputDirectory}");
        }

        $this->writeJson($outputDirectory.'/config.json', $config);

        if (! copy($imagePath, $outputDirectory.'/image.png')) {
            throw new RuntimeException("Unable to copy image to: {$outputDirectory}/image.png");
        }

        if (is_file($previewPath) && ! copy($previewPath, $outputDirectory.'/preview.png')) {
            throw new RuntimeException("Unable to copy preview to: {$outputDirectory}/preview.png");
        }

        $this->displayCreated($outputDirectory);
    }

    /** @param array<string, mixed> $manifest @param array<string, mixed> $designBrief @return array<string, string> */
    private function metadata(array $manifest, array $designBrief, string $manifestPath, string $designBriefPath): array
    {
        $fields = [
            'code' => [$manifest, 'code', $manifestPath],
            'category' => [$manifest, 'category', $manifestPath],
            'format' => [$manifest, 'format', $manifestPath],
            'color_mode' => [$manifest, 'color_mode', $manifestPath],
            'name' => [$designBrief, 'name', $designBriefPath],
            'description' => [$designBrief, 'description', $designBriefPath],
            'color_direction' => [$designBrief, 'color_direction', $designBriefPath],
            'font_mood' => [$designBrief, 'font_mood', $designBriefPath],
        ];

        $metadata = [];
        foreach ($fields as $key => [$source, $sourceKey, $path]) {
            $value = $source[$sourceKey] ?? null;
            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException("Metadata field '{$sourceKey}' is missing or invalid: {$path}");
            }

            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /** @param array<string, mixed> $config */
    private function validateConfig(array $config, string $path): void
    {
        foreach (['canvas', 'style', 'content', 'placeholders'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                throw new RuntimeException("Config is missing object '{$key}': {$path}");
            }
        }

        $aspectRatio = $config['canvas']['aspect_ratio'] ?? null;
        if (! is_string($aspectRatio) || ! preg_match('/^\d+(?:\.\d+)?:\d+(?:\.\d+)?$/', $aspectRatio)) {
            throw new RuntimeException("Config has an invalid canvas.aspect_ratio: {$path}");
        }
    }

    /** @param array<string, mixed> $config */
    private function validateImage(string $path, array $config): void
    {
        $dimensions = getimagesize($path);
        if ($dimensions === false || ($dimensions[0] ?? 0) < 1 || ($dimensions[1] ?? 0) < 1) {
            throw new RuntimeException("Unable to read image dimensions: {$path}");
        }

        $aspectRatio = (string) $config['canvas']['aspect_ratio'];
        [$expectedWidth, $expectedHeight] = array_map('floatval', explode(':', $aspectRatio));
        $actual = $dimensions[0] / $dimensions[1];
        $expected = $expectedWidth / $expectedHeight;

        if (abs($actual - $expected) / $expected > 0.05) {
            throw new RuntimeException(sprintf(
                'Image aspect ratio %.4f does not match config aspect ratio %s: %s',
                $actual,
                $aspectRatio,
                $path,
            ));
        }

        $mime = $dimensions['mime'] ?? null;
        if ($mime !== 'image/png') {
            throw new RuntimeException("Export image must be a PNG: {$path}");
        }
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
