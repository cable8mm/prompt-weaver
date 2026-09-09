<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Layout: string
{
    use EnumGetter;

    case CENTERED = 'centered';
    case EDITORIAL = 'editorial';
    case QR_FOCUS = 'qr-focus';
    case MINI_SQUARE = 'mini-square';

    public function label(): string
    {
        return match ($this) {
            self::CENTERED => 'Centered',
            self::EDITORIAL => 'Editorial',
            self::QR_FOCUS => 'QR focus',
            self::MINI_SQUARE => 'Mini Square composition',
        };
    }
}
