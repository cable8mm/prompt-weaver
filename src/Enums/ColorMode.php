<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum ColorMode: string
{
    use EnumGetter;

    case COLOR = 'color';
    case MONO = 'mono';

    public function label(): string
    {
        return match ($this) {
            self::COLOR => 'Color inkjet',
            self::MONO => 'Black-and-white laser',
        };
    }

    public static function fromCliInput(string $value): self
    {
        return self::tryFrom($value)
            ?? throw new \InvalidArgumentException(
                "Unknown color mode: {$value}".PHP_EOL.
                'Valid color modes: '.implode(', ', self::keys())
            );
    }
}
