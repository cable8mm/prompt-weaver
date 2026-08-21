<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Enums\Category;
use Cable8mm\PromptWeaver\Enums\Format;
use Cable8mm\PromptWeaver\Enums\Layout;
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
        $layouts = implode(', ', Layout::keys());

        note(<<<TEXT
Prompt Weaver CLI

Usage:
  bin/prompt-weaver init code [--category="..."] [--format="..."] [--color-mode=color|mono] [--layout="..."] [--fixtures-root=.weaver]
  bin/prompt-weaver code code [--fixtures-root=.weaver] [--dist-root=dist]
  bin/prompt-weaver calibrate code [--fixtures-root=.weaver]
  bin/prompt-weaver config-stub editorial
  bin/prompt-weaver config-stub editorial --print
  bin/prompt-weaver preview --fixture="path/to/fixture"
  bin/prompt-weaver preview --fixture="path/to/fixture" --output="path/to/preview.html"
  bin/prompt-weaver preview --code="..." [--fixtures-root=.weaver]
  bin/prompt-weaver brief code [--color-mode=color|mono] [--fixtures-root=.weaver]
  bin/prompt-weaver config code [--color-mode=color|mono] [--layout="..."] [--fixtures-root=.weaver]
  bin/prompt-weaver image code [--fixtures-root=.weaver]
  bin/prompt-weaver export code [--image=path/to/generated.png] [--output-dir=dist/code] [--fixtures-root=.weaver]
  bin/prompt-weaver chain --category="..." --format="..." --description="..." --color-direction="..." --font-mood="..." [--name="..."] --config-file=path/to/config.json
  bin/prompt-weaver pipe code [--provider=openrouter] [--api-key=sk-or-...] [--model=google/gemma-4-26b-a4b-it:free] [--color=...]
  bin/prompt-weaver pipe --category="..." --format="..." [--provider=openrouter] [--api-key=sk-or-...] [--model=google/gemma-4-26b-a4b-it:free]

Available categories:
  {$categories}

Available formats:
  {$formats}

Available layouts:
  {$layouts}
TEXT);

        return self::SUCCESS;
    }
}
