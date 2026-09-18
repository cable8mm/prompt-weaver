<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Support\PythonEnvironment;
use Illuminate\Console\Command;

final class PromptWeaverDoctorCommand extends Command
{
    protected $signature = 'prompt-weaver:doctor';

    protected $description = 'Check the Prompt Weaver Python runtime';

    public function handle(PythonEnvironment $environment): int
    {
        $checks = [
            'uv' => [$environment, ['--version']],
            'OpenCV' => [$environment, [
                '--cache-dir', $environment->cacheDirectory(),
                'run', '--locked', '--project', $environment->projectPath(),
                'python', '-c', 'import cv2; print(cv2.__version__)',
            ]],
        ];

        foreach ($checks as $label => [$runner, $arguments]) {
            $result = $runner->run($arguments);

            if ($result['exit_code'] !== 0) {
                $this->error("[FAIL] {$label}");

                if ($result['stderr'] !== '') {
                    $this->line(trim($result['stderr']));
                }

                return self::FAILURE;
            }

            $version = trim($result['stdout']);
            $this->info("[OK] {$label}".($version === '' ? '' : ": {$version}"));
        }

        $this->info('Prompt Weaver Python runtime is ready.');

        return self::SUCCESS;
    }
}
