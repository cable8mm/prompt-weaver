<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class InitCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('init')->setDescription('Create a fixture manifest.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Fixture reference in the form model/scenario.');
        $this->addOption('model', null, InputOption::VALUE_REQUIRED);
        $this->addOption('scenario', null, InputOption::VALUE_REQUIRED);
        $this->addOption('product', null, InputOption::VALUE_REQUIRED);
        $this->addOption('category', null, InputOption::VALUE_REQUIRED);
        $this->addOption('format', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        [$model, $scenario] = $this->resolveIdentifiers($input);
        $model = $this->validatePathSegment($model, 'model');
        $scenario = $this->validatePathSegment($scenario, 'scenario');

        $manifestPath = rtrim($this->fixturesRoot($input), '/').'/'.$model.'/'.$scenario.'/manifest.json';

        if (is_file($manifestPath)) {
            throw new \RuntimeException("Fixture already exists: {$manifestPath}");
        }

        $directory = dirname($manifestPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create directory: {$directory}");
        }

        $category = $input->getOption('category') ?? $this->askChoice('category', Category::cliChoices(), self::DEFAULT_CATEGORY);
        $format = $input->getOption('format') ?? $this->askChoice('format', Format::cliChoices(), self::DEFAULT_FORMAT);

        $manifest = [
            'model' => $model,
            'scenario' => $scenario,
            'product' => $input->getOption('product') ?? self::DEFAULT_PRODUCT,
            'category' => $category,
            'format' => $format,
        ];

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        if (file_put_contents($manifestPath, $json) === false) {
            throw new \RuntimeException("Unable to write manifest: {$manifestPath}");
        }

        $this->displayCreated($manifestPath);

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveIdentifiers(InputInterface $input): array
    {
        $fixtureReference = $input->getArgument('fixture');

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            return $this->parseFixtureReference($fixtureReference);
        }

        $model = $input->getOption('model') ?? $this->askText('model', 'openrouter');
        $scenario = $input->getOption('scenario') ?? $this->askText('scenario', 'cafe-restaurant');

        if ($model === '' || $scenario === '') {
            throw new \InvalidArgumentException('Missing required argument: model/scenario (e.g. init openrouter/cafe-restaurant)');
        }

        return [$model, $scenario];
    }
}
