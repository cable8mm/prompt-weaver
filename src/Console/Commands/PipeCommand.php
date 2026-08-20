<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\NanoAI\Client;
use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\ColorMode;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Pipe;
use Cable8mm\PromptWeaver\PipeResult;
use Laravel\Prompts\Progress;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;

final class PipeCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('pipe')->setDescription('Run the AI text-prompt pipeline.');
        $this->addArgument('fixture', InputArgument::OPTIONAL, 'Template code.');
        foreach (['category', 'format', 'color-mode', 'provider', 'api-key', 'model', 'color', 'fixtures-root'] as $option) {
            $this->addOption($option, null, InputOption::VALUE_REQUIRED, default: $option === 'provider' ? 'openrouter' : ($option === 'model' ? 'google/gemma-4-26b-a4b-it:free' : ($option === 'fixtures-root' ? self::DEFAULT_FIXTURES_ROOT : null)));
        }
        $this->addOption('no-progress', null, InputOption::VALUE_NONE, 'Hide pipeline progress output.');
        $this->addOption('show-output', null, InputOption::VALUE_NONE, 'Print generated prompts and JSON responses.');
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
            $category = $manifest['category'] ?? null;
            $format = $manifest['format'] ?? null;
            $colorMode = $manifest['color_mode'] ?? self::DEFAULT_COLOR_MODE;
        } else {
            $category = $input->getOption('category');
            $format = $input->getOption('format');
            $colorMode = $input->getOption('color-mode') ?? self::DEFAULT_COLOR_MODE;
        }

        $this->requireValues($category, $format);

        $progressBar = null;
        if (! $input->getOption('no-progress')) {
            $progressBar = progress('Prompt pipeline', 3, hint: 'Starting pipeline...');
            $progressBar->start();
        }

        $result = (new Pipe($client))->run(
            Category::fromCliInput($category),
            Format::fromCliInput($format),
            $input->getOption('color'),
            ColorMode::fromCliInput($colorMode),
            function (string $stage, string $message) use ($progressBar): void {
                if (! $progressBar instanceof Progress) {
                    return;
                }

                $progressBar->label($message);

                if (str_ends_with($stage, '.complete')) {
                    $progressBar->advance();
                }
            },
        );

        if ($progressBar instanceof Progress) {
            $progressBar->finish();
        }

        $fixtureDirectory = null;
        if (is_string($fixtureReference) && $fixtureReference !== '') {
            $fixtureDirectory = $this->fixtureDirectoryFromReference($fixtureReference, $this->fixturesRoot($input));
            $this->writePipelineFiles($fixtureDirectory, $result);
        }

        if ($input->getOption('show-output')) {
            $this->displaySection('design-brief prompt', $result->briefPrompt);
            $this->displaySection('design-brief response', json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->displaySection('config prompt', $result->configPrompt);
            $this->displaySection('config response', json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->displaySection('image prompt', $result->imagePrompt);
        } elseif ($fixtureDirectory !== null) {
            info("Pipeline complete. Files saved to {$fixtureDirectory}");
        } else {
            info('Pipeline complete. Use a fixture argument to save the generated files.');
        }

        return self::SUCCESS;
    }

    private function writePipelineFiles(string $directory, PipeResult $result): void
    {
        $files = [
            'brief.prompt' => $result->briefPrompt,
            'design-brief.json' => json_encode($result->briefJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'config.prompt' => $result->configPrompt,
            'raw.config.json' => json_encode($result->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'image.prompt' => $result->imagePrompt,
        ];

        foreach ($files as $filename => $contents) {
            if (file_put_contents($directory.'/'.$filename, $contents.PHP_EOL) === false) {
                throw new \RuntimeException("Unable to write pipeline output: {$directory}/{$filename}");
            }
        }
    }
}
