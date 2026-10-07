<?php

use Cable8mm\PromptWeaver\Services\WifiNotePublishService;

function run_wifi_note_publish_command(array $arguments, string $home, string $input): array
{
    $command = array_merge([PHP_BINARY, dirname(__DIR__, 2).'/bin/prompt-weaver'], $arguments);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, dirname(__DIR__, 2), array_merge($_ENV, ['HOME' => $home]));

    expect($process)->not->toBeFalse();
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exitCode' => proc_close($process),
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

it('does not upload a template pack unless the user approves', function () {
    $root = sys_get_temp_dir().'/prompt-weaver-publish-'.bin2hex(random_bytes(4));
    $home = $root.'/home';
    $dist = $root.'/dist';
    mkdir($dist.'/sample-template', 0777, true);
    file_put_contents($dist.'/sample-template/image.png', 'image');

    (new WifiNotePublishService(configPath: $home.'/.config/prompt-weaver/config.json'))
        ->saveCredentials('https://wifinote.net', 'secret-token');

    try {
        $result = run_wifi_note_publish_command([
            'publish',
            '--dist-root='.$dist,
        ], $home, "n\n");

        expect($result['exitCode'])->toBe(0);
        expect($result['stdout'])->toContain('Publish cancelled.');
        expect($result['stdout'])->not->toContain('Upload completed successfully.');
        expect($result['stderr'])->toContain('Publish template pack to WifiNote? [y/N]');
    } finally {
        remove_wifi_note_test_directory($root);
    }
});
