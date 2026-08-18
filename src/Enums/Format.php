<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Format: string
{
    use EnumGetter;

    case A45_POSTER = 'A4/A5 Poster';
    case A67_POSTER = 'A6/A7 Poster';
    case MINI_SQUARE = 'Mini Square';

    public function label(): string
    {
        return __($this->value);
    }

    public static function fromCliInput(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(
                "Unknown format: {$value}".PHP_EOL.
                'Valid formats: '.implode(', ', self::keys())
            );
    }

    public static function canvasSizeFrom(string $value): array
    {
        return match ($value) {
            self::A45_POSTER => [2480, 3508],
            self::A67_POSTER => [1240, 1748],
            self::MINI_SQUARE => [1000, 1000],
            default => throw new \InvalidArgumentException("Unknown format: {$value}".PHP_EOL.'Valid formats: '.implode(', ', self::keys())),
        };
    }
}
