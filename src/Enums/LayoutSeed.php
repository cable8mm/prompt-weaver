<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum LayoutSeed: string
{
    use EnumGetter;

    case CENTERED_FOCAL = 'clear centered focal point';
    case ASYMMETRIC_BALANCE = 'balanced asymmetrical composition';
    case GENEROUS_WHITESPACE = 'generous whitespace and quiet margins';
    case FULL_BLEED = 'bold full-bleed composition';
    case FRAMED = 'strong framed composition';
    case DIAGONAL_MOTION = 'subtle diagonal sense of movement';
    case MODULAR_GRID = 'structured modular grid';
    case LAYERED_DEPTH = 'layered foreground and background depth';
    case TOP_HEAVY = 'strong visual emphasis in the upper area';
    case LOWER_ANCHOR = 'grounded lower-area focal point';
    case BORDERED = 'decorative border framing the content';
    case RADIAL = 'radial composition around a central point';
    case STACKED_BANDS = 'stacked horizontal bands';
    case OFFSET_FRAME = 'offset frame with an intentional crop';
    case SPLIT_FIELD = 'two-zone split-field composition';
    case POSTER_WITH_MARGIN = 'poster-like composition with a defined margin';
    case FLOATING_ELEMENTS = 'sparse floating elements with open space';
    case REPETITIVE_RHYTHM = 'repeating visual rhythm across the surface';
}
