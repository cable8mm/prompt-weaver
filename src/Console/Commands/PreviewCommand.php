<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Tools\PreviewImage;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PreviewCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('preview')->setDescription('Render a fixture preview.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        $this->addOption('fixture', null, InputOption::VALUE_REQUIRED, 'Fixture directory path.');
        $this->addOption('code', null, InputOption::VALUE_REQUIRED);
        $this->addOption('output', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureDirectory = $this->fixtureDirectory($input);
        $outputOption = $input->getOption('output');
        $outputPath = $outputOption === 'html'
            ? rtrim($fixtureDirectory, '/').'/preview.html'
            : ($outputOption ?? rtrim($fixtureDirectory, '/').'/preview.png');

        $preview = new PreviewImage($fixtureDirectory);
        if (strtolower(pathinfo($outputPath, PATHINFO_EXTENSION)) === 'html') {
            $preview->renderHtml($outputPath, $input->getOptions());
        } else {
            $preview->render($outputPath, $input->getOptions());
        }

        $this->displayCreated($outputPath);

        return self::SUCCESS;
    }
}
