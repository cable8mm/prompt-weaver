<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('init')->setDescription('Create a fixture manifest.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Template code.');
        $this->addOption('category', null, InputOption::VALUE_REQUIRED);
        $this->addOption('format', null, InputOption::VALUE_REQUIRED);
        $this->addOption('color-mode', null, InputOption::VALUE_REQUIRED);
        $this->addOption('layout', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $this->validatePathSegment((string) $input->getArgument('fixture'), 'code');

        $manifestPath = rtrim($this->fixturesRoot($input), '/').'/'.$code.'/manifest.json';

        if (is_file($manifestPath)) {
            throw new \RuntimeException("Fixture already exists: {$manifestPath}");
        }

        $directory = dirname($manifestPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory: {$directory}");
        }

        $category = $input->getOption('category') ?? $this->askChoice('category', Category::keys(), self::DEFAULT_CATEGORY);
        $format = $input->getOption('format') ?? $this->askChoice('format', Format::keys(), self::DEFAULT_FORMAT);
        $colorMode = $input->getOption('color-mode') ?? $this->askChoice('color mode', ColorMode::keys(), self::DEFAULT_COLOR_MODE);
        $layout = $input->getOption('layout') ?? $this->askChoice('layout', Layout::keys(), Layout::CENTERED->value);
        $category = Category::fromCliInput($category)->value;
        $format = Format::fromCliInput($format)->value;
        $colorMode = ColorMode::fromCliInput($colorMode);
        $layout = Layout::fromCliInput($layout);

        $manifest = [
            'code' => $code,
            'category' => $category,
            'format' => $format,
            'color_mode' => $colorMode->value,
            'layout' => $layout->value,
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($manifestPath, $json) === false) {
            throw new \RuntimeException("Unable to write manifest: {$manifestPath}");
        }

        $this->displayCreated($manifestPath);

        return self::SUCCESS;
    }
}
