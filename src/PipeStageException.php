<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver;

use RuntimeException;
use Throwable;

final class PipeStageException extends RuntimeException
{
    public function __construct(
        public readonly string $stage,
        Throwable $previous,
    ) {
        $message = "Pipeline failed during {$stage}: {$previous->getMessage()}";
        $cause = $previous->getPrevious();

        while ($cause !== null) {
            $message .= sprintf(' (caused by %s: %s)', $cause::class, $cause->getMessage());
            $cause = $cause->getPrevious();
        }

        parent::__construct($message, 0, $previous);
    }
}
