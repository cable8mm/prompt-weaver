<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Support;

use RuntimeException;

final class FontPath
{
    public static function outputRegular(): string
    {
        return self::file('AtkinsonHyperlegible-Regular.ttf');
    }

    public static function outputBold(): string
    {
        return self::file('AtkinsonHyperlegible-Bold.ttf');
    }

    public static function webRegular(): string
    {
        return self::file('AtkinsonHyperlegible-Regular.woff2');
    }

    public static function webBold(): string
    {
        return self::file('AtkinsonHyperlegible-Bold.woff2');
    }

    public static function webRegularWoff(): string
    {
        return self::file('AtkinsonHyperlegible-Regular.woff');
    }

    public static function webBoldWoff(): string
    {
        return self::file('AtkinsonHyperlegible-Bold.woff');
    }

    private static function file(string $filename): string
    {
        $path = dirname(__DIR__, 2).'/fonts/'.$filename;

        if (! is_file($path)) {
            throw new RuntimeException("Font file not found: {$path}");
        }

        return $path;
    }
}
