<?php

namespace Workbench\App\Services;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Closure;

final class FixtureAiClient implements AiClient
{
    private int $structuredCalls = 0;

    public function structured(
        string $prompt,
        Closure $schema,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $this->structuredCalls++;
        $filename = $this->structuredCalls === 1 ? 'design-brief.json' : 'raw.config.json';

        return json_decode(
            (string) file_get_contents(dirname(__DIR__, 3).'/tests/Fixtures/cafe-restaurant/'.$filename),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function text(string $prompt, ?string $provider = null, ?string $model = null): string
    {
        return '';
    }

    public function image(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?string $size = null,
    ): string {
        return '';
    }
}
