<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console;

use Cable8mm\PromptWeaver\Console\Commands\BriefCommand;
use Cable8mm\PromptWeaver\Console\Commands\CalibrateCommand;
use Cable8mm\PromptWeaver\Console\Commands\ChainCommand;
use Cable8mm\PromptWeaver\Console\Commands\CodeAllCommand;
use Cable8mm\PromptWeaver\Console\Commands\CodeCommand;
use Cable8mm\PromptWeaver\Console\Commands\ConfigCommand;
use Cable8mm\PromptWeaver\Console\Commands\ConfigStubCommand;
use Cable8mm\PromptWeaver\Console\Commands\ExportAllCommand;
use Cable8mm\PromptWeaver\Console\Commands\ExportCommand;
use Cable8mm\PromptWeaver\Console\Commands\HelpCommand;
use Cable8mm\PromptWeaver\Console\Commands\ImageCommand;
use Cable8mm\PromptWeaver\Console\Commands\ImagegenCommand;
use Cable8mm\PromptWeaver\Console\Commands\InitCommand;
use Cable8mm\PromptWeaver\Console\Commands\LoginCommand;
use Cable8mm\PromptWeaver\Console\Commands\PipeCommand;
use Cable8mm\PromptWeaver\Console\Commands\PreviewCommand;
use Cable8mm\PromptWeaver\Console\Commands\PublishCommand;
use Cable8mm\PromptWeaver\Console\Commands\UnpipeCommand;
use Cable8mm\PromptWeaver\Console\Commands\ValidateConfigCommand;
use Cable8mm\PromptWeaver\Services\WifiNotePublishService;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('Prompt Weaver', self::VERSION);

        $this->setCatchExceptions(false);
        $this->setDefaultCommand('help');
        $this->addCommands([
            new HelpCommand,
            new InitCommand,
            new BriefCommand,
            new ConfigCommand,
            new ConfigStubCommand,
            new ExportCommand,
            new ExportAllCommand,
            new ValidateConfigCommand,
            new ImageCommand,
            new ImagegenCommand,
            new ChainCommand,
            new CodeCommand,
            new CodeAllCommand,
            new PipeCommand,
            new UnpipeCommand,
            new PreviewCommand,
            new CalibrateCommand,
            new LoginCommand(new WifiNotePublishService),
            new PublishCommand(new WifiNotePublishService),
        ]);
    }
}
