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
}
