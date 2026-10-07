<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Services\WifiNotePublishService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class LoginCommand extends PromptWeaverCommand
{
    public function __construct(private readonly WifiNotePublishService $publisher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('login')->setDescription('Configure WifiNote server credentials.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $server = $helper->ask($input, $output, new Question('WifiNote Server URL: '));

        $tokenQuestion = new Question('Personal Access Token: ');
        $tokenQuestion->setHidden(true)->setHiddenFallback(false);
        $token = $helper->ask($input, $output, $tokenQuestion);

        if (! is_string($server) || ! is_string($token)) {
            throw new \RuntimeException('WifiNote Server URL and Personal Access Token are required.');
        }

        $this->publisher->saveCredentials($server, $token);
        $output->writeln('WifiNote credentials saved.');

        return self::SUCCESS;
    }
}
