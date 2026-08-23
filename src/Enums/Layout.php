<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum Layout: string
{
    use EnumGetter;

    case CENTERED = 'centered';
    case EDITORIAL = 'editorial';
    case SPLIT = 'split';
    case QR_FOCUS = 'qr-focus';

    public function label(): string
    {
        return match ($this) {
            self::CENTERED => 'Centered',
            self::EDITORIAL => 'Editorial',
            self::SPLIT => 'Split composition',
            self::QR_FOCUS => 'QR focus',
        };
    }
}
