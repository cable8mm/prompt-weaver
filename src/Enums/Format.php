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

    /** @return array{0:int,1:int} */
    public function canvasDimensions(): array
    {
        return match ($this) {
            self::A45_POSTER => [210, 297],
            self::A67_POSTER => [105, 148],
            self::MINI_SQUARE => [100, 100],
        };
    }

    /** @return array<string, array{width_mm:int, height_mm:int}> */
    public static function physicalPageDimensions(): array
    {
        return [
            'a4' => ['width_mm' => 210, 'height_mm' => 297],
            'a5' => ['width_mm' => 148, 'height_mm' => 210],
            'a6' => ['width_mm' => 105, 'height_mm' => 148],
            'a7' => ['width_mm' => 74, 'height_mm' => 105],
            'mini-square' => ['width_mm' => 100, 'height_mm' => 100],
        ];
    }
}
