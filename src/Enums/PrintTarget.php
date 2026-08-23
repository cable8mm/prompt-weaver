<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum PrintTarget: string
{
    use EnumGetter;

    case BLACK_AND_WHITE_LASER = 'black-and-white laser printer safe';
    case FULL_COLOR_INKJET = 'full-color inkjet printer';
    case RGB_DISPLAY = 'RGB digital display';

    public function label(): string
    {
        return __($this->value);
    }
}
