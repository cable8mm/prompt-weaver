<?php

use Cable8mm\PromptWeaver\Console\Commands\LoginCommand;
use Cable8mm\PromptWeaver\Services\WifiNotePublishService;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Tester\CommandTester;

final class LoginCommandQuestionHelper extends QuestionHelper
{
    /** @var list<Question> */
    public array $questions = [];

    /** @param list<mixed> $answers */
    public function __construct(private array $answers)
    {
        parent::__construct();
    }

    public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        $this->questions[] = $question;

        return array_shift($this->answers);
    }
}

function remove_login_test_directory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    ) as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }

    rmdir($directory);
}

it('requests and persists WifiNote credentials without displaying the token', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-login-'.bin2hex(random_bytes(4));
    $configPath = $root.'/.config/prompt-weaver/config.json';
    $service = new WifiNotePublishService(configPath: $configPath);
    $command = new LoginCommand($service);
    $helper = new LoginCommandQuestionHelper(['https://wifinote.net', 'secret-token']);
    $command->setHelperSet(new HelperSet(['question' => $helper]));
    $tester = new CommandTester($command);

    try {
        $exitCode = $tester->execute([], ['interactive' => true]);

        expect($exitCode)->toBe(0)
            ->and($tester->getDisplay())->toContain('WifiNote credentials saved.')
            ->and($tester->getDisplay())->not->toContain('secret-token')
            ->and($helper->questions)->toHaveCount(2)
            ->and($helper->questions[0]->getQuestion())->toBe('WifiNote Server URL: ')
            ->and($helper->questions[1]->getQuestion())->toBe('Personal Access Token: ')
            ->and($helper->questions[1]->isHidden())->toBeTrue()
            ->and($helper->questions[1]->isHiddenFallback())->toBeFalse()
            ->and((new WifiNotePublishService(configPath: $configPath))->credentials())->toBe([
                'server' => 'https://wifinote.net',
                'token' => 'secret-token',
            ]);
    } finally {
        remove_login_test_directory($root);
    }
});

it('reports invalid login credentials', function (string $server, string $token, string $message) {
    $root = sys_get_temp_dir().'/prompt-weaver-login-'.bin2hex(random_bytes(4));
    $command = new LoginCommand(new WifiNotePublishService(configPath: $root.'/config.json'));
    $command->setHelperSet(new HelperSet([
        'question' => new LoginCommandQuestionHelper([$server, $token]),
    ]));
    $tester = new CommandTester($command);

    try {
        expect(fn () => $tester->execute([], ['interactive' => true]))
            ->toThrow(RuntimeException::class, $message);
    } finally {
        remove_login_test_directory($root);
    }
})->with([
    ['ftp://wifinote.net', 'token', 'valid HTTP or HTTPS URL'],
    ['https://wifinote.net', '', 'must not be empty'],
]);

it('reports credential persistence failures', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-login-'.bin2hex(random_bytes(4));
    mkdir($root);
    file_put_contents($root.'/not-a-directory', 'file');
    $command = new LoginCommand(new WifiNotePublishService(
        configPath: $root.'/not-a-directory/config.json',
    ));
    $command->setHelperSet(new HelperSet([
        'question' => new LoginCommandQuestionHelper(['https://wifinote.net', 'secret-token']),
    ]));
    $tester = new CommandTester($command);

    try {
        expect(fn () => $tester->execute([], ['interactive' => true]))
            ->toThrow(RuntimeException::class, 'Unable to create config directory');
    } finally {
        remove_login_test_directory($root);
    }
});
