<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Validators;

use Cable8mm\PromptWeaver\Enums\PrintTarget;
use RuntimeException;

final class ConfigValidator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function validate(array $config, ?string $source = null): void
    {
        foreach (['canvas', 'style', 'content', 'placeholders'] as $key) {
            if (! isset($config[$key]) || ! is_array($config[$key])) {
                throw new RuntimeException($this->message(
                    "Config is missing object '{$key}'",
                    $source,
                ));
            }
        }

        $aspectRatio = $config['canvas']['aspect_ratio'] ?? null;
        if (
            ! is_string($aspectRatio)
            || ! preg_match('/^\d+(?:\.\d+)?:\d+(?:\.\d+)?$/', $aspectRatio)
        ) {
            throw new RuntimeException($this->message(
                'Config has an invalid canvas.aspect_ratio',
                $source,
            ));
        }

        $printTarget = $config['style']['print_target'] ?? null;
        if (! is_string($printTarget) || PrintTarget::tryFrom($printTarget) === null) {
            throw new RuntimeException($this->message(
                'Config has an invalid style.print_target',
                $source,
            ));
        }
    }

    private function message(string $message, ?string $source): string
    {
        return $message.($source === null ? '.' : ": {$source}");
    }
}
