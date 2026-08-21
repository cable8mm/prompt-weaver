<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Contracts;

use Closure;

interface AiClient
{
    /**
     * @param  Closure(object): array<string, object>  $schema
     * @return array<string, mixed>
     */
    public function structured(
        string $prompt,
        Closure $schema,
        ?string $provider = null,
        ?string $model = null,
    ): array;

    public function text(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): string;

    /**
     * Return the first generated image as binary contents.
     */
    public function image(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?string $size = null,
    ): string;
}
