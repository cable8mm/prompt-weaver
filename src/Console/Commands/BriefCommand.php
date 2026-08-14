<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\DesignBriefPrompt;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BriefCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('brief')->setDescription('Generate a design-brief prompt.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Fixture reference in the form model/scenario.');
        $this->addOption('product', null, InputOption::VALUE_REQUIRED);
        $this->addOption('category', null, InputOption::VALUE_REQUIRED);
        $this->addOption('format', null, InputOption::VALUE_REQUIRED);
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

        $prompt = new DesignBriefPrompt(
            product: $product,
            category: Category::fromCliInput($category),
            format: Format::fromCliInput($format),
        );
        $prompt->build();
        $promptText = $prompt->prompt();

        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $promptPath = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input)).'/brief.prompt';
            $this->writeFile($promptPath, $promptText.PHP_EOL);
        }

        echo $promptText.PHP_EOL;

        return self::SUCCESS;
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Unable to write prompt: {$path}");
        }
    }
}
