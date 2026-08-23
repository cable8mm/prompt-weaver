<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum ColorMode: string
{
    use EnumGetter;

    // These values are part of the CLI and manifest format. Keep them
    // title-cased to match the other human-readable enum values.
    case COLOR = 'Color';
    case MONO = 'Mono';

    public function label(): string
    {
        return __($this->value);
    }
}
