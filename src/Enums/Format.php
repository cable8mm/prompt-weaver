<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum WifiNoteFormat: string
{
    use EnumGetter;

    case A45_POSTER = 'A4/A5 Poster Type';
    case L_STAND = 'L-Shape Stand / Table Tent Type';
    case STICKER = 'Sticker Type (Round/Square)';
    case CARD = 'Business Card Type';

    public function label(): string
    {
        return __($this->value);
    }
}
