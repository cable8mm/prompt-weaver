<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class UnpipeCommand extends PromptWeaverCommand
{
    /** @var array<int, string> */
    private const GENERATED_FILES = [
        'brief.prompt',
        'design-brief.json',
        'config.prompt',
        'raw.config.json',
        'image.prompt',
        'config.json',
        'image.png',
        'preview.png',
    ];

    protected function configure(): void
    {
        $this->setName('unpipe')->setDescription('Remove generated pipe files from a fixture.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Fixture code.');
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip the confirmation prompt.');
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureDirectory = $this->fixtureDirectoryFromReference(
            (string) $input->getArgument('fixture'),
            $this->fixturesRoot($input),
        );

        if (! is_dir($fixtureDirectory)) {
            throw new RuntimeException("Fixture directory not found: {$fixtureDirectory}");
        }

        $paths = array_values(array_filter(
            array_map(
                fn (string $filename): string => $fixtureDirectory.'/'.$filename,
                self::GENERATED_FILES,
            ),
            is_file(...),
        ));

        if ($paths === []) {
            note('No generated pipe files found.');

            return self::SUCCESS;
        }

        if (! $input->getOption('force')) {
            if (! stream_isatty(STDIN)) {
                throw new RuntimeException('Unpipe requires confirmation. Use --force for non-interactive runs.');
            }

            if (! confirm(
                label: 'Remove '.count($paths).' generated file(s) from '.$fixtureDirectory.'?',
                default: false,
            )) {
                note('Cancelled.');

                return self::SUCCESS;
            }
        }

        foreach ($paths as $path) {
            if (! unlink($path)) {
                throw new RuntimeException("Unable to remove generated file: {$path}");
            }

            info("Removed {$path}");
        }

        return self::SUCCESS;
    }
}
