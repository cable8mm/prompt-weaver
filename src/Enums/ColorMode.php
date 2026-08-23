<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum ColorMode: string
{
    use EnumGetter;

    case COLOR = 'Color';
    case MONO = 'Mono';

    public function label(): string
    {
        return __($this->value);
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
