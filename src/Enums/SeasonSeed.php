<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum SeasonSeed: string
{
    use EnumGetter;

    case SPRING_CHERRY_BLOSSOM = 'spring cherry blossom mood';
    case SUMMER_BEACH_CITRUS = 'summer beach and citrus mood';
    case AUTUMN_AMBER_MAPLE = 'autumn amber and maple mood';
    case WINTER_FROST_PINE = 'winter frost and pine mood';
    case TIMELESS = 'no specific season, timeless mood';
    case EARLY_MORNING = 'quiet early morning mood';
    case GOLDEN_HOUR = 'warm golden hour mood';
    case NIGHT_CITY = 'calm late-night city mood';
    case RAINY_DAY = 'quiet rainy day mood';
    case FRESH_AFTER_RAIN = 'fresh atmosphere after rain';
    case LATE_SPRING = 'light late-spring freshness';
    case HIGH_SUMMER = 'bright midsummer energy';
    case EARLY_AUTUMN = 'crisp early-autumn air';
    case DEEP_WINTER = 'deep winter stillness';
    case DUSK = 'soft blue-hour dusk mood';
    case WEEKEND_MORNING = 'relaxed weekend morning mood';
    case FESTIVAL_SEASON = 'lively festival-season mood';
    case INDOOR_COZY = 'cozy indoor seasonal mood';
}
