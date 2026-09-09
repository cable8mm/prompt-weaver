<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Laravel;

use Cable8mm\PromptWeaver\Contracts\AiClient;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
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
        $response = $this->withRetries('structured', $provider, $model, function () use ($prompt, $schema, $provider, $model) {
            return (new StructuredAnonymousAgent(
                instructions: 'Return only the structured data requested by the user. Do not add commentary.',
                messages: [],
                tools: [],
                schema: $schema,
            ))->prompt($prompt, provider: $provider, model: $model, timeout: $this->timeout());
        });

        return $response->toArray();
    }

    public function text(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): string {
        return (string) $this->withRetries('text', $provider, $model, function () use ($prompt, $provider, $model) {
            return (new AnonymousAgent(
                instructions: 'Follow the user instructions exactly.',
                messages: [],
                tools: [],
            ))->prompt($prompt, provider: $provider, model: $model, timeout: $this->timeout());
        });
    }

    public function image(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?string $size = null,
    ): string {
        return (string) $this->withRetries('image', $provider, $model, function () use ($prompt, $provider, $model, $size) {
            $request = Image::of($prompt)->timeout($this->timeout());

            if ($size !== null) {
                $request->size($size);
            }

            return $request->generate(provider: $provider, model: $model)->firstImage()->content();
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function withRetries(string $operation, ?string $provider, ?string $model, callable $operationCallback): mixed
    {
        $retries = max(0, (int) config('prompt-weaver.ai.retries', 2));
        $sleepMs = max(0, (int) config('prompt-weaver.ai.retry_sleep_ms', 500));
        $attempts = $retries + 1;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $operationCallback();
            } catch (Throwable $exception) {
                $willRetry = $attempt < $attempts && $this->isRetryable($exception);

                if ($willRetry) {
                    $delayMs = $sleepMs * (2 ** ($attempt - 1));
                    $this->logger->warning('Retrying AI provider request.', [
                        'operation' => $operation,
                        'provider' => $provider,
                        'model' => $model,
                        'attempt' => $attempt,
                        'max_attempts' => $attempts,
                        'retry_in_ms' => $delayMs,
                        'exception' => $exception,
                    ]);

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }

                    continue;
                }

                $this->logProviderFailure($operation, $provider, $model, $exception, $attempt);

                throw $exception;
            }
        }

        throw new \LogicException('AI provider retry loop ended unexpectedly.');
    }

    private function timeout(): int
    {
        return max(1, (int) config('prompt-weaver.ai.timeout', 20));
    }

    private function isRetryable(Throwable $exception): bool
    {
        if ($exception instanceof ProviderConnectionException
            || $exception instanceof RateLimitedException
            || $exception instanceof ProviderOverloadedException
            || $exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && ($exception->response?->status() === 429 || $exception->response?->serverError());
    }

    private function logProviderFailure(
        string $operation,
        ?string $provider,
        ?string $model,
        Throwable $exception,
        int $attempt,
    ): void {
        $this->logger->error('AI provider request failed.', [
            'operation' => $operation,
            'provider' => $provider,
            'model' => $model,
            'attempt' => $attempt,
            'exception' => $exception,
        ]);
    }
}
