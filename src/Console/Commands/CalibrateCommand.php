<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Tools\PreviewImage;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CalibrateCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('calibrate')->setDescription('Calibrate fixture placeholder coordinates.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        $this->addOption('fixture', null, InputOption::VALUE_REQUIRED, 'Fixture directory path.');
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureDirectory = $this->fixtureDirectory($input);
        (new PreviewImage($fixtureDirectory))->calibrate();

        $this->displayUpdated(rtrim($fixtureDirectory, '/').'/calibrate.config.json');

        return self::SUCCESS;
    }
}
