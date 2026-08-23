<?php

namespace Cable8mm\PromptWeaver\Tools;

final class PythonQrDetector
{
    /**
     * @param  array<string, mixed>  $placeholder
     * @return array{left:float, top:float, width:float, height:float, center_x:float, center_y:float}|null
     */
    public function detect(string $imagePath, array $placeholder): ?array
    {
        $scriptPath = dirname(__DIR__, 2).'/scripts/calibrate_qr.py';
        if (! is_file($scriptPath)) {
            return null;
        }

        $projectPath = dirname(__DIR__, 2);
        $python = getenv('PROMPT_WEAVER_PYTHON');
        $command = $python === false || $python === ''
            ? [$this->uvBinary(), '--cache-dir', $this->uvCacheDir(), 'run', '--locked', '--project', $projectPath, $scriptPath]
            : [$python, $scriptPath];
        $command = [
            ...$command,
            '--image', $imagePath,
            '--x', (string) ($placeholder['x_pc'] ?? 0),
            '--y', (string) ($placeholder['y_pc'] ?? 0),
            '--width', (string) ($placeholder['width_pc'] ?? 0),
        ];
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ! is_string($stdout) || trim($stdout) === '') {
            return null;
        }

        try {
            $result = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($result) || ! $this->hasGeometry($result)) {
            return null;
        }

        return [
            'left' => (float) $result['left'],
            'top' => (float) $result['top'],
            'width' => (float) $result['width'],
            'height' => (float) $result['height'],
            'center_x' => (float) $result['center_x'],
            'center_y' => (float) $result['center_y'],
        ];
    }

    private function uvBinary(): string
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

    private function uvCacheDir(): string
    {
        if (($cacheDir = getenv('PROMPT_WEAVER_UV_CACHE_DIR')) !== false && $cacheDir !== '') {
            return $cacheDir;
        }

        if (function_exists('config')) {
            $cacheDir = config('prompt-weaver.uv.cache_dir');

            if (is_string($cacheDir) && $cacheDir !== '') {
                return $cacheDir;
            }
        }

        return sys_get_temp_dir().'/prompt-weaver-uv';
    }

    /** @param array<string, mixed> $result */
    private function hasGeometry(array $result): bool
    {
        foreach (['left', 'top', 'width', 'height', 'center_x', 'center_y'] as $key) {
            if (! isset($result[$key]) || ! is_numeric($result[$key])) {
                return false;
            }
        }

        return $result['width'] > 0 && $result['height'] > 0;
    }
}
