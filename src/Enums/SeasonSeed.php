<?php

namespace Cable8mm\PromptWeaver\Enums;

enum SeasonSeed: string
{
    case SPRING_CHERRY_BLOSSOM = 'spring cherry blossom mood';
    case SUMMER_BEACH_CITRUS = 'summer beach and citrus mood';
    case AUTUMN_AMBER_MAPLE = 'autumn amber and maple mood';
    case WINTER_FROST_PINE = 'winter frost and pine mood';
    case TIMELESS = 'no specific season, timeless mood';
    case EARLY_MORNING = 'quiet early morning mood';
    case GOLDEN_HOUR = 'warm golden hour mood';
    case NIGHT_CITY = 'calm late-night city mood';
}
