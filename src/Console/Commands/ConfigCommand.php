<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\ConfigPrompt;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('config')->setDescription('Generate a config prompt.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Fixture reference in the form model/scenario.');
        $this->addOption('brief', null, InputOption::VALUE_REQUIRED);
        $this->addOption('color-direction', null, InputOption::VALUE_REQUIRED);
        $this->addOption('font-mood', null, InputOption::VALUE_REQUIRED);
        $this->addOption('concept-name', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureReference = $input->getArgument('fixture');

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $designBriefJson = $this->readJsonFile($this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/design-brief.json');
            $designBrief = $designBriefJson['design_brief'] ?? null;
            $colorDirection = $designBriefJson['color_direction'] ?? null;
            $fontMood = $designBriefJson['font_mood'] ?? null;
            $conceptName = $designBriefJson['concept_name'] ?? null;
        } else {
            $designBrief = $input->getOption('brief');
            $colorDirection = $input->getOption('color-direction');
            $fontMood = $input->getOption('font-mood');
            $conceptName = $input->getOption('concept-name');
        }

        $this->requireValues($designBrief, $colorDirection, $fontMood);

        $prompt = new ConfigPrompt(
            designBrief: $designBrief,
            colorDirection: $colorDirection,
            fontMood: $fontMood,
            conceptName: $conceptName,
        );
        $prompt->build();
        $promptText = $prompt->prompt();

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $promptPath = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/config.prompt';

            if (file_put_contents($promptPath, $promptText.PHP_EOL) === false) {
                throw new \RuntimeException("Unable to write prompt: {$promptPath}");
            }
        }

        echo $promptText.PHP_EOL;

        return self::SUCCESS;
    }
}
