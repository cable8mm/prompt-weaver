<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Category: string
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

    /**
     * @return array<int, string>
     */
    public static function cliChoices(): array
    {
        return [
            'Cafe/Restaurant',
            'Office/Coworking',
            'Stay/Hotel',
            'Event/Exhibition',
            'Other',
        ];
    }

    public static function fromCliInput(string $value): self
    {
        return match ($value) {
            'Cafe/Restaurant', 'Cafe / Restaurant' => self::CAFE_RESTAURANT,
            'Office/Coworking', 'Office / Coworking Space' => self::OFFICE_COWORKING,
            'Stay/Hotel', 'Stay / Hotel Type' => self::STAY_HOTEL,
            'Event/Exhibition', 'Event / Exhibition Type' => self::EVENT_EXHIBITION,
            'Other' => self::OTHER,
            default => throw new \InvalidArgumentException("Unknown category: {$value}".PHP_EOL.'Valid categories: '.implode(', ', self::cliChoices())),
        };
    }
}
