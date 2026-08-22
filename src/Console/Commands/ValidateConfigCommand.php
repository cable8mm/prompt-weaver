<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Validators\ConfigValidator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ValidateConfigCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this
            ->setName('config:validate')
            ->setDescription('Validate a config JSON file.')
            ->addArgument('config', InputArgument::REQUIRED, 'Config JSON file path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('config');
        $config = $this->readJsonFile($path);

        (new ConfigValidator)->validate($config, $path);
        $output->writeln('<info>Config is valid.</info>');

        return self::SUCCESS;
    }
}
