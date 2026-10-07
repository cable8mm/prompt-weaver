<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Services\WifiNotePublishService;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class PublishCommand extends PromptWeaverCommand
{
    public function __construct(private readonly WifiNotePublishService $publisher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('publish')->setDescription('Upload a template pack to WifiNote.');
        $this->addOption('dist-root', null, InputOption::VALUE_REQUIRED, 'Template directory.', 'dist');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->publisher->credentials();
        $archivePath = $this->publisher->createTemplatePack((string) $input->getOption('dist-root'));

        try {
            $confirmation = new ConfirmationQuestion('Publish template pack to WifiNote? [y/N] ', false);
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');

            if (! $helper->ask($input, $output, $confirmation)) {
                $output->writeln('Publish cancelled.');

                return self::SUCCESS;
            }

            $uploadResult = $this->publisher->upload($archivePath);
            $output->writeln("Upload completed successfully (HTTP {$uploadResult['status_code']}).");
            $output->writeln('WifiNote accepted the Template Pack for import.');
            $output->writeln('Upload ID: '.$uploadResult['upload_id']);

            return self::SUCCESS;
        } finally {
            $this->publisher->removeTemplatePack($archivePath);
        }
    }
}
