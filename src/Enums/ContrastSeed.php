<?php

namespace Cable8mm\PromptWeaver\Enums;

enum ContrastSeed: string
{
    case HIGH_CONTRAST = 'high-contrast graphic treatment';
    case SOFT_CONTRAST = 'soft low-contrast tonal layering';
    case DARK_LIGHT = 'dramatic dark-and-light balance';
    case MUTED = 'muted and restrained contrast';
    case VIBRANT_ACCENT = 'quiet base with one vibrant accent';
    case MONOCHROME_TONES = 'rich monochrome tonal range';
    case OUTLINE_SHADOW = 'clear outline and soft shadow contrast';
    case AIRY = 'bright airy tonal separation';
    case DEEP_ACCENT = 'deep dark base with a warm accent';
    case PASTEL_SEPARATION = 'gentle pastel color separation';
    case BLACK_CREAM = 'ink black against warm cream';
    case COOL_WARM = 'balanced cool and warm contrast';
    case LIGHT_ON_DARK = 'light graphic elements on a dark field';
    case DARK_ON_LIGHT = 'dark graphic elements on a light field';
    case LIMITED_PALETTE = 'limited two-tone palette';
    case TACTILE_SHADOW = 'tactile shadows with restrained highlights';
    case GRADUAL_TONES = 'gradual tonal transitions';
    case CRISP_EDGES = 'crisp edges with minimal tonal noise';
}
