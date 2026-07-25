<?php

if (! function_exists('__')) {
    function __(?string $key = null, array $replace = [], ?string $locale = null): string
    {
        if (is_null($key)) {
            return '';
        }

        foreach ($replace as $placeholder => $value) {
            $key = str_replace(':'.$placeholder, $value, $key);
        }

        return $key;
    }
}
