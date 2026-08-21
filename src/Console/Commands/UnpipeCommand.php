<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UnpipeCommand extends PromptWeaverCommand
{
    /** @var array<int, string> */
    private const PIPE_FILES = [
        'brief.prompt',
        'design-brief.json',
        'config.prompt',
        'raw.config.json',
        'image.prompt',
    ];

    /** @var array<int, string> */
    private const ALL_FILES = [
        'config.json',
        'image.png',
        'preview.png',
    ];

    protected function configure(): void
    {
        $this->setName('unpipe')->setDescription('Remove generated pipe files from a fixture.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Fixture code.');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Also remove config.json, image.png, and preview.png.');
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

        $filenames = self::PIPE_FILES;
        if ($input->getOption('all')) {
            $filenames = [...$filenames, ...self::ALL_FILES];
        }

        $paths = array_values(array_filter(
            array_map(
                fn (string $filename): string => $fixtureDirectory.'/'.$filename,
                $filenames,
            ),
            is_file(...),
        ));

        if ($paths === []) {
            $output->writeln('<info>No generated pipe files found.</info>');

            return self::SUCCESS;
        }

        if (! $input->getOption('force')) {
            if (! stream_isatty(STDIN)) {
                throw new RuntimeException('Unpipe requires confirmation. Use --force for non-interactive runs.');
            }

            $question = new ConfirmationQuestion(
                'Remove '.count($paths).' generated file(s) from '.$fixtureDirectory.'? [y/N] ',
                false,
            );
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            if (! $helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Cancelled.</comment>');

                return self::SUCCESS;
            }
        }

        foreach ($paths as $path) {
            if (! unlink($path)) {
                throw new RuntimeException("Unable to remove generated file: {$path}");
            }

            $output->writeln('<info>Removed '.$path.'</info>');
        }

        return self::SUCCESS;
    }
}
