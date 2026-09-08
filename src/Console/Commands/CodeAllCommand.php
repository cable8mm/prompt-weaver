<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Tools\CodeRenamer;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CodeAllCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('code-all')->setDescription('Rename every fixture using config.json style.theme.');
        $this->addFixturesRootOption();
        $this->addOption('dist-root', null, InputOption::VALUE_REQUIRED, 'Export root directory.', 'dist');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the rename plan without changing files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixturesRoot = $this->fixturesRoot($input);
        if (! is_dir($fixturesRoot)) {
            throw new RuntimeException("Fixture root directory not found: {$fixturesRoot}");
        }

        $fixtureCodes = array_values(array_filter(
            scandir($fixturesRoot) ?: [],
            fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_dir(rtrim($fixturesRoot, '/').'/'.$entry),
        ));
        sort($fixtureCodes, SORT_STRING);

        if ($fixtureCodes === []) {
            throw new RuntimeException("No fixture directories found: {$fixturesRoot}");
        }

        $renamer = new CodeRenamer;
        $plans = [];
        $newCodes = [];
        $distRoot = (string) $input->getOption('dist-root');

        foreach ($fixtureCodes as $oldCode) {
            $this->validatePathSegment($oldCode, 'code');
            $plan = $renamer->plan($oldCode, $fixturesRoot, $distRoot);

            if ($plan['newCode'] !== $oldCode && isset($newCodes[$plan['newCode']])) {
                throw new RuntimeException(
                    "Multiple fixtures derive the same code '{$plan['newCode']}': "
                    .$newCodes[$plan['newCode']].", {$oldCode}",
                );
            }

            $newCodes[$plan['newCode']] = $oldCode;
            $plans[] = $plan;
        }

        foreach ($plans as $plan) {
            $output->writeln($plan['oldCode'].' -> '.$plan['newCode']);
        }

        if ($input->getOption('dry-run')) {
            return self::SUCCESS;
        }

        foreach ($plans as $plan) {
            $this->displayUpdated($renamer->apply($plan));
        }

        return self::SUCCESS;
    }
}
