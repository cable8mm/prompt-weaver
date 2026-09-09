<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Laravel;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Closure;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Image;
use Laravel\Ai\StructuredAnonymousAgent;
use Psr\Log\LoggerInterface;
use Throwable;

final class LaravelAiClient implements AiClient
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function structured(
        string $prompt,
        Closure $schema,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        try {
            $response = (new StructuredAnonymousAgent(
                instructions: 'Return only the structured data requested by the user. Do not add commentary.',
                messages: [],
                tools: [],
                schema: $schema,
            ))->prompt($prompt, provider: $provider, model: $model);
        } catch (Throwable $exception) {
            $this->logProviderFailure('structured', $provider, $model, $exception);

            throw $exception;
        }

        return $response->toArray();
    }

    public function text(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): string {
        try {
            return (string) (new AnonymousAgent(
                instructions: 'Follow the user instructions exactly.',
                messages: [],
                tools: [],
            ))->prompt($prompt, provider: $provider, model: $model);
        } catch (Throwable $exception) {
            $this->logProviderFailure('text', $provider, $model, $exception);

            throw $exception;
        }
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

        try {
            return $request->generate(provider: $provider, model: $model)->firstImage()->content();
        } catch (Throwable $exception) {
            $this->logProviderFailure('image', $provider, $model, $exception);

            throw $exception;
        }
    }

    private function logProviderFailure(
        string $operation,
        ?string $provider,
        ?string $model,
        Throwable $exception,
    ): void {
        $this->logger->error('AI provider request failed.', [
            'operation' => $operation,
            'provider' => $provider,
            'model' => $model,
            'exception' => $exception,
        ]);
    }
}
