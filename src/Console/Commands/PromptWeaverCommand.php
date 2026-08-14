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
use function Laravel\Prompts\text;

abstract class PromptWeaverCommand extends Command
{
    protected const DEFAULT_FIXTURES_ROOT = 'tests/Fixtures';

    protected const DEFAULT_PRODUCT = 'a Wi-Fi signage template';

    protected const DEFAULT_CATEGORY = 'Cafe/Restaurant';

    protected const DEFAULT_FORMAT = 'A4/A5 Poster';

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
            $model = $input->getOption('model');
            $scenario = $input->getOption('scenario');

            $this->requireValues($model, $scenario);
            $fixtureDirectory = rtrim($this->fixturesRoot($input), '/').'/'.$this->validatePathSegment($model, 'model').'/'.$this->validatePathSegment($scenario, 'scenario');
        }

        if (! is_dir($fixtureDirectory)) {
            throw new RuntimeException("Fixture directory not found: {$fixtureDirectory}");
        }

        return $fixtureDirectory;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function parseFixtureReference(string $reference): array
    {
        $parts = explode('/', trim($reference, '/'));

        if (count($parts) !== 2 || in_array('', $parts, true)) {
            throw new \InvalidArgumentException("Fixture must be in the form model/scenario: {$reference}");
        }

        return [
            $this->validatePathSegment($parts[0], 'model'),
            $this->validatePathSegment($parts[1], 'scenario'),
        ];
    }

    protected function fixtureDirectoryFromReference(string $reference, string $fixturesRoot): string
    {
        [$model, $scenario] = $this->parseFixtureReference($reference);

        return rtrim($fixturesRoot, '/').'/'.$model.'/'.$scenario;
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

    protected function askText(string $label, string $example): string
    {
        if (! stream_isatty(STDIN)) {
            return '';
        }

        return text("Enter {$label}", placeholder: $example);
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
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Fixture reference in the form model/scenario.');

        return $this;
    }
}
