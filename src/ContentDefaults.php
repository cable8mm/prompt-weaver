<?php

declare(strict_types=1);

namespace Cable8mm\PromptWeaver;

use Cable8mm\PromptWeaver\Enums\Layout;

final class ContentDefaults
{
    /**
     * @return array<string, array{text: string, x_pc: int, y_pc: int, align: string}>
     */
    public static function forLayout(Layout $layout): array
    {
        return match ($layout) {
            Layout::CENTERED => self::content(50, 10, 'center', 50, 62, 50, 96),
            Layout::EDITORIAL => self::content(32, 12, 'left', 50, 58, 50, 95),
            Layout::SPLIT => self::content(27, 12, 'left', 70, 61, 50, 96),
            Layout::QR_FOCUS => self::content(50, 8, 'center', 50, 50, 50, 95),
            Layout::MINI_SQUARE => self::contentWithoutTitle(50, 80, 50, 96),
        };
    }

    /**
     * @return array<string, array{text: string, x_pc: int, y_pc: int, align: string}>
     */
    private static function content(int $titleX, int $titleY, string $titleAlign, int $messageX, int $messageY, int $footerX, int $footerY): array
    {
        return [
            'title' => [
                'text' => '와이파이 연결',
                'x_pc' => $titleX,
                'y_pc' => $titleY,
                'align' => $titleAlign,
            ],
            ...self::contentWithoutTitle($messageX, $messageY, $footerX, $footerY),
        ];
    }

    /**
     * @return array{message: array{text: string, x_pc: int, y_pc: int, align: string}, footer: array{text: string, x_pc: int, y_pc: int, align: string}}
     */
    private static function contentWithoutTitle(int $messageX, int $messageY, int $footerX, int $footerY): array
    {
        return [
            'message' => [
                'text' => '스캔하여 연결하세요.',
                'x_pc' => $messageX,
                'y_pc' => $messageY,
                'align' => 'center',
            ],
            'footer' => [
                'text' => '제작: WIFI NOTE',
                'x_pc' => $footerX,
                'y_pc' => $footerY,
                'align' => 'center',
            ],
        ];
    }
}
