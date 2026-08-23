<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\ImagePrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ChainCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('chain')->setDescription('Generate all three prompts in one run.');
        foreach ([
            'category',
            'format',
            'color-mode',
            'description',
            'color-direction',
            'font-mood',
            'name',
            'config-file',
        ] as $option) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $category = $input->getOption('category');
        $format = $input->getOption('format');
        $colorMode = ColorMode::fromKey($input->getOption('color-mode') ?? self::DEFAULT_COLOR_MODE->value);
        $description = $input->getOption('description');
        $colorDirection = $input->getOption('color-direction');
        $fontMood = $input->getOption('font-mood');
        $name = $input->getOption('name');
        $configFile = $input->getOption('config-file');

        $this->requireValues($category, $format, $description, $colorDirection, $fontMood, $configFile);

        if (! is_file($configFile)) {
            throw new \RuntimeException("Config file not found: {$configFile}");
        }

        $format = Format::fromKey($format);
        $briefPrompt = new DesignBriefPrompt(Category::fromKey($category), $format, colorMode: $colorMode);
        $briefPrompt->build();

        $configPrompt = new ConfigPrompt($description, $colorDirection, $fontMood, $format, name: $name, colorMode: $colorMode);
        $configPrompt->build();

        $imagePrompt = new ImagePrompt($this->readJsonFile($configFile));
        $imagePrompt->build();

        $this->displaySection('design-brief prompt', $briefPrompt->prompt());
        $this->displaySection('config prompt', $configPrompt->prompt());
        $this->displaySection('image prompt', $imagePrompt->prompt());

        return self::SUCCESS;
    }
}
