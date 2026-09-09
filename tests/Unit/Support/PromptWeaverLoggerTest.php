<?php

use Cable8mm\PromptWeaver\Support\PromptWeaverLogger;

it('creates a standalone logger and writes to its configured file', function () {
    $directory = sys_get_temp_dir().'/prompt-weaver-logger-'.bin2hex(random_bytes(4));
    $path = $directory.'/prompt-weaver.log';

    try {
        PromptWeaverLogger::standalone($path)->error('Test provider failure', [
            'provider' => 'openrouter',
        ]);

        expect($path)->toBeFile()
            ->and(file_get_contents($path))->toContain('Test provider failure')
            ->and(file_get_contents($path))->toContain('openrouter');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});
