<?php

namespace Cable8mm\PromptWeaver\Tools\Traits;

trait WifiHelperTrait
{
    private function buildWifiPayload(string $ssid, string $password): string
    {
        return sprintf(
            'WIFI:T:WPA;S:%s;P:%s;;',
            $this->escapeWifiValue($ssid),
            $this->escapeWifiValue($password),
        );
    }

    private function escapeWifiValue(string $value): string
    {
        return str_replace(['\\', ';', ',', ':'], ['\\\\', '\\;', '\\,', '\\:'], $value);
    }
}
