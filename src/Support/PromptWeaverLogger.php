<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Support;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class PromptWeaverLogger
{
    public static function standalone(string $path): LoggerInterface
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Unable to create log directory: {$directory}");
        }

        return new Logger('prompt-weaver', [
            new StreamHandler($path, Logger::DEBUG),
        ]);
    }
}
