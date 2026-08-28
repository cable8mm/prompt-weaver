<?php

use Cable8mm\PromptWeaver\Tools\Code;

it('derives a fixture code from a theme', function () {
    expect((new Code)->deriveFromTheme('A timeless industrial cafe mood blending vintage concrete texture'))
        ->toBe('a-timeless-industrial-cafe');
});

it('rejects a theme without a usable code', function () {
    (new Code)->deriveFromTheme('---');
})->throws(RuntimeException::class);
