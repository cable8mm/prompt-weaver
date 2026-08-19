<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Symfony\Component\Console\Command\HelpCommand as SymfonyHelpCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\note;

final class HelpCommand extends SymfonyHelpCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $categories = implode(', ', Category::keys());
        $formats = implode(', ', Format::keys());

        note(<<<TEXT
Prompt Weaver CLI

Usage:
  bin/prompt-weaver init code [--product="..."] [--category="..."] [--format="..."] [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver calibrate code [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver preview --fixture="path/to/fixture"
  bin/prompt-weaver preview --fixture="path/to/fixture" --output="path/to/preview.html"
  bin/prompt-weaver preview --code="..." [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver brief code [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver config code [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver image code [--fixtures-root=tests/Fixtures]
  bin/prompt-weaver chain --product="..." --category="..." --format="..." --brief="..." --color-direction="..." --font-mood="..." [--concept-name="..."] --config-file=path/to/config.json
  bin/prompt-weaver pipe code [--provider=openrouter] [--api-key=sk-or-...] [--model=google/gemma-4-26b-a4b-it:free] [--color=...]
  bin/prompt-weaver pipe --product="..." --category="..." --format="..." [--provider=openrouter] [--api-key=sk-or-...] [--model=google/gemma-4-26b-a4b-it:free]

Available categories:
  {$categories}

Available formats:
  {$formats}
TEXT);

        return self::SUCCESS;
    }
}
