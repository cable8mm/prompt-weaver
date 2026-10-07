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
        parent::__construct("Pipeline failed during {$stage}: {$previous->getMessage()}", 0, $previous);
    }
}
