<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\ConfigPrompt;
use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
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
            'brief',
            'color-direction',
            'font-mood',
            'concept-name',
            'config-file',
        ] as $option) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $category = $input->getOption('category');
        $format = $input->getOption('format');
        $brief = $input->getOption('brief');
        $colorDirection = $input->getOption('color-direction');
        $fontMood = $input->getOption('font-mood');
        $conceptName = $input->getOption('concept-name');
        $configFile = $input->getOption('config-file');

        $this->requireValues($category, $format, $brief, $colorDirection, $fontMood, $configFile);

        if (! is_file($configFile)) {
            throw new \RuntimeException("Config file not found: {$configFile}");
        }

        $briefPrompt = new DesignBriefPrompt(Category::fromCliInput($category), Format::fromCliInput($format));
        $briefPrompt->build();

        $configPrompt = new ConfigPrompt($brief, $colorDirection, $fontMood, $conceptName);
        $configPrompt->build();

        $imagePrompt = new ImagePrompt($this->readJsonFile($configFile));
        $imagePrompt->build();

        $this->displaySection('design-brief prompt', $briefPrompt->prompt());
        $this->displaySection('config prompt', $configPrompt->prompt());
        $this->displaySection('image prompt', $imagePrompt->prompt());

        return self::SUCCESS;
    }
}
