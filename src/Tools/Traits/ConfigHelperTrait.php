<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

use InvalidArgumentException;

trait ConfigHelperTrait
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function configPlaceholder(array $config, string $key): array
    {
        $placeholder = $config['placeholders'][$key] ?? null;

        if (! is_array($placeholder)) {
            throw new InvalidArgumentException("Missing placeholder: {$key}");
        }

        return $placeholder;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<int, string>  $path
     */
    private function configValue(array $config, array $path): mixed
    {
        $value = $config;

        foreach ($path as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, mixed>  $config
     */
    private function optionValue(array $options, array $config, string $key, string $default): string
    {
        $value = $options[$key] ?? $this->configValue($config, ['placeholders', $key, 'value']);

        if (! is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }
}
