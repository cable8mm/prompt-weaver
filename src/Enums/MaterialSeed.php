<?php

namespace Cable8mm\PromptWeaver\Enums;

enum MaterialSeed: string
{
    case MATTE_PAPER = 'matte paper stock';
    case RECYCLED_PAPER = 'recycled paper fibers';
    case CERAMIC = 'smooth ceramic surface';
    case BRUSHED_METAL = 'subtle brushed metal';
    case NATURAL_WOOD = 'natural light wood';
    case FROSTED_GLASS = 'frosted glass';
    case CANVAS = 'soft canvas';
    case VELVET = 'rich velvet';
    case INK_ON_PAPER = 'ink printed on textured paper';
}
