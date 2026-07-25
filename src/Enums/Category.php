<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum WifiNoteCategory: string
{
    use EnumGetter;

    case CAFE_RESTAURANT = 'Cafe / Restaurant';
    case OFFICE_COWORKING = 'Office / Coworking Space';
    case STAY_HOTEL = 'Stay / Hotel Type';
    case EVENT_EXHIBITION = 'Event / Exhibition Type';
    case OTHER = 'Other';

    public function label(): string
    {
        return __($this->value);
    }
}
