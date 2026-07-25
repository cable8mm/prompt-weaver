<?php

namespace Cable8mm\PromptWeaver\Tools;

use RuntimeException;

final class RenderHtml
{
    use Traits\ConfigHelperTrait;
    use Traits\PlaceholderGeometryTrait;
    use Traits\QrHelperTrait;
    use Traits\WifiHelperTrait;

    private const DEFAULT_SSID = 'WIFI-NOTE';

    private const DEFAULT_PASSWORD = 'WIFI-PASSWORD';

    /**
     * Create a browser preview that keeps image.png and the calibrated config
     * as external files. The QR is embedded so the HTML has no QR dependency.
     *
     * @param  array<string, string>  $options
     */
    public function render(string $fixtureDirectory, string $outputPath, array $options = []): string
    {
        $fixtureDirectory = rtrim($fixtureDirectory, '/');
        $configPath = is_file($fixtureDirectory.'/calibrate.config.json')
            ? $fixtureDirectory.'/calibrate.config.json'
            : $fixtureDirectory.'/config.json';
        $backgroundPath = $fixtureDirectory.'/image.png';

        if (! is_file($configPath)) {
            throw new RuntimeException("Config file not found: {$configPath}");
        }
        if (! is_file($backgroundPath)) {
            throw new RuntimeException("Background image not found: {$backgroundPath}");
        }

        /** @var array<string, mixed> $config */
        $config = json_decode((string) file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
        $dimensions = getimagesize($backgroundPath);
        if ($dimensions === false) {
            throw new RuntimeException("Unable to read background image dimensions: {$backgroundPath}");
        }

        $ssid = $this->optionValue($options, $config, 'ssid', self::DEFAULT_SSID);
        $password = $this->optionValue($options, $config, 'password', self::DEFAULT_PASSWORD);
        $qrPayload = $options['qr-payload'] ?? $this->configValue($config, ['placeholders', 'qr', 'payload']);
        if (! is_string($qrPayload) || $qrPayload === '') {
            $qrPayload = $this->buildWifiPayload($ssid, $password);
        }

        $ssidPlaceholder = $this->configPlaceholder($config, 'ssid');
        $passwordPlaceholder = $this->configPlaceholder($config, 'password');
        $qrPlaceholder = $this->configPlaceholder($config, 'qr');
        $qrDataUri = 'data:image/png;base64,'.base64_encode($this->qrBinary($qrPlaceholder, $qrPayload, $dimensions[0], $dimensions[1]));
        $width = $dimensions[0];
        $height = $dimensions[1];

        $textElement = static function (string $class, array $placeholder): string {
            $x = (float) ($placeholder['box_x_pc'] ?? 0);
            $y = (float) ($placeholder['box_y_pc'] ?? 0);
            $fontSize = (int) ($placeholder['font_size_px'] ?? 36);
            $color = htmlspecialchars((string) ($placeholder['color'] ?? '#111111'), ENT_QUOTES, 'UTF-8');
            $style = "left: {$x}%; top: {$y}%; --font-size: {$fontSize}; color: {$color};";

            return '<div id="'.$class.'" class="text-placeholder" style="'.$style.'"></div>';
        };

        $qrX = (float) ($qrPlaceholder['box_x_pc'] ?? $qrPlaceholder['x_pc'] ?? 0);
        $qrY = (float) ($qrPlaceholder['box_y_pc'] ?? $qrPlaceholder['y_pc'] ?? 0);
        $qrBox = $this->qrPlaceholderBoxFromDimensions($qrPlaceholder, $width, $height);
        $qrBoxSize = min($qrBox['width'], $qrBox['height']);
        $qrPadding = max(10, (int) round($qrBoxSize * 0.1));
        $qrWidth = (($qrBoxSize - ($qrPadding * 2)) / $width) * 100;
        $fontUrl = htmlspecialchars($this->relativePath(dirname($outputPath), $this->webFontPath()), ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html>'.PHP_EOL
            .'<html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'.PHP_EOL
            .'<title>Prompt Weaver Preview</title><style>'.PHP_EOL
            .'*{box-sizing:border-box}@font-face{font-family:PreviewFont;src:url("'.$fontUrl.'") format("woff2");font-weight:400;font-style:normal;font-display:block}body{margin:0;background:#ddd;display:grid;place-items:center;min-height:100vh}.preview{position:relative;width:min(100vw,'.$width.'px);aspect-ratio:'.$width.'/'.$height.';container-type:inline-size}.preview>img.background{position:absolute;inset:0;width:100%;height:100%;display:block}.text-placeholder{position:absolute;transform:translate(-50%,-50%);width:70%;text-align:center;font-family:PreviewFont,sans-serif;font-weight:400;line-height:1;white-space:nowrap;font-size:calc(var(--font-size) * 100cqw / '.$width.')} .qr{position:absolute;transform:translate(-50%,-50%);width:'.$qrWidth.'%;height:auto;image-rendering:auto}</style></head><body>'.PHP_EOL
            .'<main class="preview"><img class="background" src="image.png" alt="">'
            .$textElement('ssid', $ssidPlaceholder).$textElement('password', $passwordPlaceholder)
            .'<img class="qr" src="'.$qrDataUri.'" alt="Wi-Fi QR code" style="left: '.$qrX.'%; top: '.$qrY.'%;"></main>'.PHP_EOL
            .'<script>fetch("'.htmlspecialchars(basename($configPath), ENT_QUOTES, 'UTF-8').'" ).then(r=>r.json()).then(c=>{document.querySelector("#ssid").textContent=c.placeholders?.ssid?.value??'.json_encode($ssid, JSON_THROW_ON_ERROR).';document.querySelector("#password").textContent=c.placeholders?.password?.value??'.json_encode($password, JSON_THROW_ON_ERROR).';}).catch(()=>{document.querySelector("#ssid").textContent='.json_encode($ssid, JSON_THROW_ON_ERROR).';document.querySelector("#password").textContent='.json_encode($password, JSON_THROW_ON_ERROR).';});</script></body></html>'.PHP_EOL;

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
        if (file_put_contents($outputPath, $html) === false) {
            throw new RuntimeException("Unable to write preview HTML: {$outputPath}");
        }

        return $outputPath;
    }

    private function webFontPath(): string
    {
        $fontPath = dirname(__DIR__, 2).'/fonts/AtkinsonHyperlegible-Regular.woff2';

        if (is_file($fontPath)) {
            return $fontPath;
        }

        throw new RuntimeException("Webfont file not found: {$fontPath}");
    }

    private function relativePath(string $fromDirectory, string $targetPath): string
    {
        $from = realpath($fromDirectory);
        $target = realpath($targetPath);

        if ($from === false || $target === false) {
            throw new RuntimeException('Unable to resolve preview font path.');
        }

        $fromParts = explode('/', trim($from, '/'));
        $targetParts = explode('/', trim($target, '/'));
        $commonLength = 0;

        while (isset($fromParts[$commonLength], $targetParts[$commonLength]) && $fromParts[$commonLength] === $targetParts[$commonLength]) {
            $commonLength++;
        }

        return str_repeat('../', count($fromParts) - $commonLength).implode('/', array_slice($targetParts, $commonLength));
    }
}
