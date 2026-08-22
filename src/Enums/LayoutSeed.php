<?php

namespace Cable8mm\PromptWeaver\Enums;

enum LayoutSeed: string
{
    case CENTERED_FOCAL = 'clear centered focal point';
    case ASYMMETRIC_BALANCE = 'balanced asymmetrical composition';
    case GENEROUS_WHITESPACE = 'generous whitespace and quiet margins';
    case FULL_BLEED = 'bold full-bleed composition';
    case FRAMED = 'strong framed composition';
    case DIAGONAL_MOTION = 'subtle diagonal sense of movement';
    case MODULAR_GRID = 'structured modular grid';
    case LAYERED_DEPTH = 'layered foreground and background depth';
}
