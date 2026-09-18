<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Console\Commands;

use Cable8mm\PromptWeaver\Support\PythonEnvironment;
use Illuminate\Console\Command;

final class PromptWeaverInstallCommand extends Command
{
    protected $signature = 'prompt-weaver:install';

    protected $description = 'Install Prompt Weaver Python dependencies from the lockfile';

    public function handle(PythonEnvironment $environment): int
    {
        $this->info('Installing Prompt Weaver Python dependencies...');

        $result = $environment->run([
            '--cache-dir', $environment->cacheDirectory(),
            'sync', '--locked', '--project', $environment->projectPath(),
        ]);

        if ($result['exit_code'] !== 0) {
            $this->error('Prompt Weaver Python environment installation failed.');

            if ($result['stderr'] !== '') {
                $this->line(trim($result['stderr']));
            }

            return self::FAILURE;
        }

        $this->info('Prompt Weaver Python environment is ready.');

        return self::SUCCESS;
    }
}
