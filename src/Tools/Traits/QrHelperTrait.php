<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;

trait QrHelperTrait
{
    /**
     * @param  array<string, mixed>  $placeholder
     */
    private function qrBinary(array $placeholder, string $payload, int $imageWidth, int $imageHeight): string
    {
        $box = $this->qrPlaceholderBoxFromDimensions($placeholder, $imageWidth, $imageHeight);
        $boxSize = min($box['width'], $box['height']);
        $padding = max(10, (int) round($boxSize * 0.1));
        $qrSize = max(64, $boxSize - ($padding * 2));

        $writer = new Writer(new GDLibRenderer($qrSize, 0));
        $qrBinary = $writer->writeString($payload, 'UTF-8', ErrorCorrectionLevel::H());

        return $qrBinary;
    }
}
