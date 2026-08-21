<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Laravel;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Closure;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Image;
use Laravel\Ai\StructuredAnonymousAgent;

final class LaravelAiClient implements AiClient
{
    public function structured(
        string $prompt,
        Closure $schema,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $response = (new StructuredAnonymousAgent(
            instructions: 'Return only the structured data requested by the user. Do not add commentary.',
            messages: [],
            tools: [],
            schema: $schema,
        ))->prompt($prompt, provider: $provider, model: $model);

        return $response->toArray();
    }

    public function text(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): string {
        return (string) (new AnonymousAgent(
            instructions: 'Follow the user instructions exactly.',
            messages: [],
            tools: [],
        ))->prompt($prompt, provider: $provider, model: $model);
    }

    public function image(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?string $size = null,
    ): string {
        $request = Image::of($prompt);

        if ($size !== null) {
            $request->size($size);
        }

        return $request->generate(provider: $provider, model: $model)->firstImage()->content();
    }
}
