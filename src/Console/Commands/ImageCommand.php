<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\ImagePrompt;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImageCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('image')->setDescription('Generate an image prompt.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        $this->addOption('config-file', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureReference = $input->getArgument('fixture');
        $configPath = is_string($fixtureReference) && $fixtureReference !== ''
            ? $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/config.json'
            : $input->getOption('config-file');

        $this->requireValues($configPath);

        if (! is_file($configPath)) {
            throw new \RuntimeException("Config file not found: {$configPath}");
        }

        $prompt = new ImagePrompt($this->readJsonFile($configPath));
        $prompt->build();
        $promptText = $prompt->prompt();

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $promptPath = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/image.prompt';

            if (file_put_contents($promptPath, $promptText.PHP_EOL) === false) {
                throw new \RuntimeException("Unable to write prompt: {$promptPath}");
            }
        }

        echo $promptText.PHP_EOL;

        return self::SUCCESS;
    }
}
