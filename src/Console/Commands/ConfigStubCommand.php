<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Enums\Layout;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ConfigStubCommand extends PromptWeaverCommand
{
    protected function configure(): void
    {
        $this->setName('config-stub')->setDescription('Assemble a config stub into the image preview prompt.');
        $this->addArgument('layout', InputArgument::REQUIRED, 'Config stub layout.');
        $this->addOption('print', null, InputOption::VALUE_NONE, 'Print the assembled prompt instead of copying it.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $layout = Layout::fromCliInput((string) $input->getArgument('layout'));
        $this->copyPromptToClipboard($layout->value, (bool) $input->getOption('print'));

        return self::SUCCESS;
    }

    private function copyPromptToClipboard(string $layout, bool $print): void
    {
        $templatePath = dirname(__DIR__, 3).'/prompts/preview.prompt';
        $configPath = dirname(__DIR__, 3).'/stubs/config.'.$layout.'.prompt';
        $template = file_get_contents($templatePath);
        $configPrompt = file_get_contents($configPath);

        if ($template === false || $configPrompt === false) {
            throw new RuntimeException('Unable to read the preview prompt template.');
        }

        $prompt = str_replace('{{ config_prompt }}', trim($configPrompt), $template);

        if ($print) {
            echo $prompt;

            return;
        }

        $clipboardCommand = $this->clipboardCommand();
        $process = proc_open($clipboardCommand, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to open the clipboard command.');
        }

        fwrite($pipes[0], $prompt);
        fclose($pipes[0]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Unable to copy the config stub prompt to the clipboard: '.trim((string) $error));
        }

        echo "Copied config stub prompt for {$layout} to clipboard.".PHP_EOL;
    }

    /**
     * @return array<int, string>
     */
    private function clipboardCommand(): array
    {
        foreach (['pbcopy', 'wl-copy', 'xclip'] as $binary) {
            exec('command -v '.escapeshellarg($binary), $output, $exitCode);

            if ($exitCode === 0) {
                return $binary === 'xclip' ? [$binary, '-selection', 'clipboard'] : [$binary];
            }
        }

        throw new RuntimeException('No clipboard command found. Install pbcopy, wl-copy, or xclip, or use --print.');
    }
}
