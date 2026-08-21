<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('config')->setDescription('Generate a config prompt.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        $this->addOption('description', null, InputOption::VALUE_REQUIRED);
        $this->addOption('color-direction', null, InputOption::VALUE_REQUIRED);
        $this->addOption('font-mood', null, InputOption::VALUE_REQUIRED);
        $this->addOption('format', null, InputOption::VALUE_REQUIRED);
        $this->addOption('color-mode', null, InputOption::VALUE_REQUIRED);
        $this->addOption('name', null, InputOption::VALUE_REQUIRED);
        $this->addOption('layout', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureReference = $input->getArgument('fixture');

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $designBriefJson = $this->readJsonFile($this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/design-brief.json');
            $description = $designBriefJson['description'] ?? null;
            $colorDirection = $designBriefJson['color_direction'] ?? null;
            $fontMood = $designBriefJson['font_mood'] ?? null;
            $name = $designBriefJson['name'] ?? null;
            $manifest = $this->readJsonFile($this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/manifest.json');
            $format = isset($manifest['format']) ? Format::fromCliInput($manifest['format']) : null;
            $colorMode = ColorMode::fromCliInput($manifest['color_mode'] ?? self::DEFAULT_COLOR_MODE);
            $layout = Layout::fromCliInput($manifest['layout'] ?? Layout::CENTERED->value);
        } else {
            $description = $input->getOption('description');
            $colorDirection = $input->getOption('color-direction');
            $fontMood = $input->getOption('font-mood');
            $name = $input->getOption('name');
            $format = $input->getOption('format');
            $colorMode = $input->getOption('color-mode') ?? self::DEFAULT_COLOR_MODE;
            $layout = Layout::fromCliInput($input->getOption('layout') ?? Layout::CENTERED->value);
        }

        $this->requireValues($description, $colorDirection, $fontMood);
        if (! $format instanceof Format) {
            $this->requireValues($format);
            $format = Format::fromCliInput($format);
        }

        $prompt = new ConfigPrompt(
            description: $description,
            colorDirection: $colorDirection,
            fontMood: $fontMood,
            format: $format,
            colorMode: $colorMode instanceof ColorMode ? $colorMode : ColorMode::fromCliInput($colorMode),
            name: $name,
            layout: $layout,
        );
        $prompt->build();
        $promptText = $prompt->prompt();

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $promptPath = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/config.prompt';

            if (file_put_contents($promptPath, $promptText.PHP_EOL) === false) {
                throw new \RuntimeException("Unable to write prompt: {$promptPath}");
            }

            $this->displayCreated($promptPath);
        } else {
            echo $promptText.PHP_EOL;
        }

        return self::SUCCESS;
    }
}
