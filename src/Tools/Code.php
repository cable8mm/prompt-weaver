<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver\Tools;

use RuntimeException;

final class Code
{
    private const MAX_CODE_WORDS = 4;

    private const MAX_CODE_LENGTH = 48;

    /**
     * Derive a fixture code from a theme string.
     *
     * This tool is intentionally filesystem-agnostic so it can be used by
     * applications that do not store fixtures as files.
     */
    public function deriveFromTheme(string $theme): string
    {
        $words = preg_split('/\s+/', trim($theme), self::MAX_CODE_WORDS + 1, PREG_SPLIT_NO_EMPTY) ?: [];
        $code = strtolower(implode(' ', array_slice($words, 0, self::MAX_CODE_WORDS)));
        $code = preg_replace('/[^a-z0-9]+/i', '-', $code) ?? '';
        $code = trim($code, '-');

        if (strlen($code) > self::MAX_CODE_LENGTH) {
            $code = substr($code, 0, self::MAX_CODE_LENGTH);
            $code = rtrim($code, '-');
            $code = substr($code, 0, strrpos($code, '-') ?: strlen($code));
        }

        $code = trim($code, '-');
        if ($code === '') {
            throw new RuntimeException("Unable to derive a code from style.theme: {$theme}");
        }

        return $code;
    }
}
