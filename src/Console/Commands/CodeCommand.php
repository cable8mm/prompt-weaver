<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Tools\CodeRenamer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CodeCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('code')->setDescription('Rename a fixture using config.json style.theme.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Current fixture code.');
        $this->addFixturesRootOption();
        $this->addOption('dist-root', null, InputOption::VALUE_REQUIRED, 'Export root directory.', 'dist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $distRoot = (string) $input->getOption('dist-root');
        $plan = (new CodeRenamer)->plan(
            $this->validatePathSegment((string) $input->getArgument('fixture'), 'code'),
            $this->fixturesRoot($input),
            $distRoot,
        );
        $updated = (new CodeRenamer)->apply($plan);
        $this->displayUpdated($updated);

        return self::SUCCESS;
    }
}
