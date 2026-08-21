<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ImagegenCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('imagegen')->setDescription('Generate an image from image.prompt.');
        $this->addArgument('fixture', InputArgument::REQUIRED, 'Template code.');
        $this->addOption('provider', null, InputOption::VALUE_REQUIRED, default: getenv('IMAGEGEN_PROVIDER') ?: 'gemini');
        $this->addOption('model', null, InputOption::VALUE_REQUIRED, default: getenv('IMAGEGEN_MODEL') ?: 'gemini-3.1-flash-image-preview');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output image path.');
        $this->addFixturesRootOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fixtureDirectory = $this->fixtureDirectoryFromReference(
            (string) $input->getArgument('fixture'),
            $this->fixturesRoot($input),
        );
        $promptPath = $fixtureDirectory.'/image.prompt';

        if (! is_file($promptPath)) {
            throw new \RuntimeException("Image prompt not found: {$promptPath}");
        }

        $prompt = file_get_contents($promptPath);

        if ($prompt === false || trim($prompt) === '') {
            throw new \RuntimeException("Image prompt is empty: {$promptPath}");
        }

        if (! function_exists('app')) {
            throw new \RuntimeException('Image generation requires a Laravel application.');
        }

        $outputPath = $input->getOption('output');
        $outputPath = is_string($outputPath) && $outputPath !== ''
            ? $outputPath
            : $fixtureDirectory.'/image.png';

        $contents = app(AiClient::class)->image(
            trim($prompt),
            $input->getOption('provider'),
            $input->getOption('model'),
        );

        if (file_put_contents($outputPath, $contents) === false) {
            throw new \RuntimeException("Unable to write generated image: {$outputPath}");
        }

        $this->displayCreated($outputPath);

        return self::SUCCESS;
    }
}
