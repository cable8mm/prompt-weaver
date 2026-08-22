<?php

namespace Cable8mm\PromptWeaver\Enums;

use Cable8mm\EnumGetter\EnumGetter;

enum TextureSeed: string
{
    use EnumGetter;

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
    case LINEN_FIBER = 'fine linen fiber texture';
    case RECYCLED_FLECKS = 'small recycled-paper flecks';
    case WOOD_GRAIN = 'subtle natural wood grain';
    case CONCRETE_GRAIN = 'fine architectural concrete grain';
    case WAX_CRAYON = 'soft wax-crayon marks';
    case PENCIL_HATCHING = 'delicate pencil hatching';
    case CUT_PAPER_EDGES = 'layered cut-paper edges';
    case SCREEN_PRINT = 'slightly imperfect screen-print texture';
    case EMBOSSED_LINES = 'quiet embossed line detail';
    case SMOOTH_FLAT = 'clean smooth flat surface';
}
