<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Category: string
{
    use EnumGetter;

    case CAFE_RESTAURANT = 'Cafe/Restaurant';
    case OFFICE_COWORKING = 'Office/Coworking';
    case STAY_HOTEL = 'Stay/Hotel';
    case EVENT_EXHIBITION = 'Event/Exhibition';
    case OTHER = 'Other';

    public function label(): string
    {
        return __($this->value);
    }
}
