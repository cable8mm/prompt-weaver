<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PipeCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('pipe')->setDescription('Run the complete AI prompt pipeline.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        foreach (['product', 'category', 'format', 'provider', 'api-key', 'model', 'color', 'fixtures-root'] as $option) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED, default: $option === 'provider' ? 'openrouter' : ($option === 'model' ? 'google/gemma-4-26b-a4b-it:free' : ($option === 'fixtures-root' ? self::DEFAULT_FIXTURES_ROOT : null)));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = (string) $input->getOption('provider');
        $apiKey = $input->getOption('api-key');
        $model = (string) $input->getOption('model');
        $client = new Client(provider: $provider, apiKey: $apiKey, model: $model);

        $fixtureReference = $input->getArgument('fixture');
        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $manifest = $this->readJsonFile($this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/manifest.json');
            $product = $manifest['product'] ?? null;
            $category = $manifest['category'] ?? null;
            $format = $manifest['format'] ?? null;
        } else {
            $product = $input->getOption('product');
            $category = $input->getOption('category');
            $format = $input->getOption('format');
        }

        $this->requireValues($product, $category, $format);
        $result = (new Pipe($client))->run(
            $product,
            Category::fromCliInput($category),
            Format::fromCliInput($format),
            $input->getOption('color'),
        );

        $this->displaySection('design-brief prompt', $result->briefPrompt);
        $this->displaySection('design-brief response', json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->displaySection('config prompt', $result->configPrompt);
        $this->displaySection('config response', json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->displaySection('image prompt', $result->imagePrompt);

        return self::SUCCESS;
    }
}
