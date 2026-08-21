<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportAllCommand extends ExportCommand
{
    protected function configure(): void
    {
        $this->setName('export-all')->setDescription('Package every fixture for Laravel import.');
        $this->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Output root directory.', 'dist');
        $this->addFixturesRootOption();
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

        $outputRoot = (string) $input->getOption('output-dir');
        foreach ($fixtureCodes as $code) {
            $this->validatePathSegment($code, 'code');
            $this->exportFixture(
                $code,
                $fixturesRoot,
                null,
                rtrim($outputRoot, '/').'/'.$code,
            );
        }

        return self::SUCCESS;
    }
}
