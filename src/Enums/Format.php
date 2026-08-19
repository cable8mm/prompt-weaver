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

    public function pages(): array
    {
        return match ($this) {
            self::A45_POSTER => ['a4', 'a5'],
            self::A67_POSTER => ['a6', 'a7'],
            self::MINI_SQUARE => ['mini-square'],
        };
    }

    public function ratio(): string
    {
        return match ($this) {
            self::A45_POSTER => '5:7',
            self::A67_POSTER => '5:7',
            self::MINI_SQUARE => '1:1'
        };
    }
}
