<?php

use Cable8mm\PromptWeaver\Support\FontPath;

it('provides absolute paths for output fonts', function () {
    expect(FontPath::outputRegular())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Regular.ttf')
        ->and(FontPath::outputBold())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Bold.ttf');

    expect(is_file(FontPath::outputRegular()))->toBeTrue();
    expect(is_file(FontPath::outputBold()))->toBeTrue();
});

it('provides absolute paths for web fonts', function () {
    expect(FontPath::webRegular())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Regular.woff2')
        ->and(FontPath::webBold())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Bold.woff2')
        ->and(FontPath::webRegularWoff())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Regular.woff')
        ->and(FontPath::webBoldWoff())
        ->toEndWith('/fonts/AtkinsonHyperlegible-Bold.woff');

    expect(is_file(FontPath::webRegular()))->toBeTrue();
    expect(is_file(FontPath::webBold()))->toBeTrue();
    expect(is_file(FontPath::webRegularWoff()))->toBeTrue();
    expect(is_file(FontPath::webBoldWoff()))->toBeTrue();
});
