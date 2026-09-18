<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Support;

use RuntimeException;

final class PythonEnvironment
{
    /** @return array{exit_code:int, stdout:string, stderr:string} */
    public function run(array $arguments): array
    {
        $command = [$this->binary(), ...$arguments];
        $pipes = [];
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->projectPath(),
            $this->processEnvironment(),
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start uv.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exit_code' => proc_close($process),
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    public function binary(): string
    {
        if (($binary = getenv('PROMPT_WEAVER_UV')) !== false && $binary !== '') {
            return $binary;
        }

        if (function_exists('config')) {
            $binary = config('prompt-weaver.uv.binary');

            if (is_string($binary) && $binary !== '') {
                return $binary;
            }
        }

        return 'uv';
    }

    public function projectPath(): string
    {
        return dirname(__DIR__, 2);
    }

    public function projectEnvironment(): string
    {
        if (($environment = getenv('UV_PROJECT_ENVIRONMENT')) !== false && $environment !== '') {
            return $environment;
        }

        if (function_exists('config')) {
            $environment = config('prompt-weaver.uv.project_environment');

            if (is_string($environment) && $environment !== '') {
                return $environment;
            }
        }

        return sys_get_temp_dir().'/prompt-weaver-venv';
    }

    public function cacheDirectory(): string
    {
        if (($cacheDirectory = getenv('PROMPT_WEAVER_UV_CACHE_DIR')) !== false && $cacheDirectory !== '') {
            return $cacheDirectory;
        }

        if (function_exists('config')) {
            $cacheDirectory = config('prompt-weaver.uv.cache_dir');

            if (is_string($cacheDirectory) && $cacheDirectory !== '') {
                return $cacheDirectory;
            }
        }

        return sys_get_temp_dir().'/prompt-weaver-uv';
    }

    /** @return array<string, string> */
    public function processEnvironment(): array
    {
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];

        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                $environment[$key] = $value;
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (is_string($value)) {
                $environment[$key] = $value;
            }
        }

        $environment['UV_PROJECT_ENVIRONMENT'] = $this->projectEnvironment();

        return $environment;
    }
}
