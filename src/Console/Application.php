<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console;

use Cable8mm\PromptWeaver\Console\Commands\BriefCommand;
use Cable8mm\PromptWeaver\Console\Commands\CalibrateCommand;
use Cable8mm\PromptWeaver\Console\Commands\ChainCommand;
use Cable8mm\PromptWeaver\Console\Commands\CodeCommand;
use Cable8mm\PromptWeaver\Console\Commands\ConfigCommand;
use Cable8mm\PromptWeaver\Console\Commands\ConfigStubCommand;
use Cable8mm\PromptWeaver\Console\Commands\ExportAllCommand;
use Cable8mm\PromptWeaver\Console\Commands\ExportCommand;
use Cable8mm\PromptWeaver\Console\Commands\HelpCommand;
use Cable8mm\PromptWeaver\Console\Commands\ImageCommand;
use Cable8mm\PromptWeaver\Console\Commands\ImagegenCommand;
use Cable8mm\PromptWeaver\Console\Commands\InitCommand;
use Cable8mm\PromptWeaver\Console\Commands\PipeCommand;
use Cable8mm\PromptWeaver\Console\Commands\PreviewCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('Prompt Weaver', '1.0.0');

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
            new ImageCommand,
            new ImagegenCommand,
            new ChainCommand,
            new CodeCommand,
            new PipeCommand,
            new PreviewCommand,
            new CalibrateCommand,
        ]);
    }
}
