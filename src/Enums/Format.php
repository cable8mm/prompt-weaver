<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Format: string
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

    /**
     * @return array<int, string>
     */
    public static function cliChoices(): array
    {
        return [
            'A4/A5 Poster',
            'L-Stand/Table Tent',
            'Sticker',
            'Business Card',
        ];
    }

    public static function fromCliInput(string $value): self
    {
        return match ($value) {
            'A4/A5 Poster', 'A4/A5 Poster Type' => self::A45_POSTER,
            'L-Stand/Table Tent', 'L-Shape Stand / Table Tent Type' => self::L_STAND,
            'Sticker', 'Sticker Type (Round/Square)' => self::STICKER,
            'Business Card', 'Business Card Type' => self::CARD,
            default => throw new \InvalidArgumentException("Unknown format: {$value}".PHP_EOL.'Valid formats: '.implode(', ', self::cliChoices())),
        };
    }
}
