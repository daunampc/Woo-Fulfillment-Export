<?php

defined('ABSPATH') || exit;

final class WFE_Array_Helper
{
    public static function get($array, string $path, $default = '')
    {
        if (!is_array($array) || $path === '') {
            return $default;
        }

        $current = $array;
        foreach (explode('.', $path) as $segment) {
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
                continue;
            }

            return $default;
        }

        return $current;
    }
}
