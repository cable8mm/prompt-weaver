<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

abstract class PromptWeaverCommand extends Command
{
    /**
     * Working files are kept outside the checked-in test fixtures by default.
     * The option name is retained for backwards compatibility and because the
     * working directory still contains fixture-shaped input files.
     */
    protected const DEFAULT_FIXTURES_ROOT = '.weaver';

    protected const DEFAULT_CATEGORY = 'Cafe/Restaurant';

    protected const DEFAULT_FORMAT = 'A4/A5 Poster';

    protected const DEFAULT_COLOR_MODE = 'mono';

    protected function fixturesRoot(InputInterface $input): string
    {
        return (string) $input->getOption('fixtures-root');
    }

    protected function fixtureDirectory(InputInterface $input): string
    {
        $fixtureDirectory = $input->getOption('fixture');

        if ($fixtureDirectory === null && $input->hasArgument('fixture')) {
            $fixtureReference = $input->getArgument('fixture');

            if (is_string($fixtureReference) && $fixtureReference !== '') {
                $fixtureDirectory = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input));
            }
        }

        if (! is_string($fixtureDirectory) || $fixtureDirectory === '') {
            $code = $input->getOption('code');

            $this->requireValues($code);
            $fixtureDirectory = rtrim($this->fixturesRoot($input), '/').'/'.$this->validatePathSegment($code, 'code');
        }

        if (! is_dir($fixtureDirectory)) {
            throw new RuntimeException("Fixture directory not found: {$fixtureDirectory}");
        }

        return $fixtureDirectory;
    }

    /**
     * @return array{0: string}
     */
    protected function parseFixtureReference(string $reference): array
    {
        $code = trim($reference, '/');

        if ($code === '' || str_contains($code, '/')) {
            throw new \InvalidArgumentException("Fixture code must be a single path segment: {$reference}");
        }

        return [$this->validatePathSegment($code, 'code')];
    }

    protected function fixtureDirectoryFromReference(string $reference, string $fixturesRoot): string
    {
        [$code] = $this->parseFixtureReference($reference);

        return rtrim($fixturesRoot, '/').'/'.$code;
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJsonFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("JSON file not found: {$path}");
        }

        $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($json)) {
            throw new RuntimeException("JSON object expected: {$path}");
        }

        return $json;
    }

    protected function requireValues(string ...$values): void
    {
        foreach ($values as $value) {
            if ($value === '') {
                throw new \InvalidArgumentException('Missing required argument.');
            }
        }
    }

    protected function validatePathSegment(string $value, string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$name}: {$value}");
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $choices
     */
    protected function askChoice(string $label, array $choices, string $default): string
    {
        if (! stream_isatty(STDIN)) {
            return $default;
        }

        $defaultIndex = array_search($default, $choices, true);
        $selection = select(
            label: "Select a {$label}",
            options: $choices,
            default: $defaultIndex === false ? null : $defaultIndex,
        );

        return $choices[(int) $selection] ?? $default;
    }

    protected function displaySection(string $title, ?string $content): void
    {
        echo "=== {$title} ===".PHP_EOL;
        echo ($content ?? '').PHP_EOL.PHP_EOL;
    }

    protected function displayCreated(string $path): void
    {
        info("Created {$path}");
    }

    protected function displayUpdated(string $path): void
    {
        info("Updated {$path}");
    }

    protected function addFixturesRootOption(): static
    {
        $this->addOption('fixtures-root', null, InputOption::VALUE_REQUIRED, 'Fixture root directory.', self::DEFAULT_FIXTURES_ROOT);

        return $this;
    }

    protected function addFixtureArgument(): static
    {
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');

        return $this;
    }
}
