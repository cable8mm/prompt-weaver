<?php

namespace Cable8mm\PromptWeaver\Enums;

enum TextureSeed: string
{
    case SUBTLE_GRID = 'subtle grid pattern';
    case HAND_DRAWN_LINE = 'organic hand-drawn line texture';
    case HALFTONE_DOTS = 'halftone dot pattern';
    case GRADIENT_MESH = 'soft gradient mesh';
    case GEOMETRIC_LINE_ART = 'geometric line-art pattern';
    case PAPER_CRAFT = 'paper/craft texture';
    case INK_GRAIN = 'fine ink grain and risograph texture';
    case FABRIC_WEAVE = 'subtle woven fabric texture';
    case TILE_PATTERN = 'decorative ceramic tile pattern';
    case BRUSH_STROKES = 'expressive dry-brush strokes';
}
