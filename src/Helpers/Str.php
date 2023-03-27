<?php

namespace CSVDB\Helpers;

class Str
{
    public static function starts_with(string $haystack, string $needle): bool
    {
        if (!function_exists('str_starts_with')) {
            return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
        } else {
            return str_starts_with($haystack, $needle);
        }
    }

    public static function ends_with(string $haystack, string $needle): bool
    {
        if (!function_exists('str_ends_with')) {
            return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
        } else {
            return str_ends_with($haystack, $needle);
        }
    }

    public static function contains(string $haystack, string $needle): bool
    {
        if (!function_exists('str_contains')) {
            return $needle !== '' && mb_strpos($haystack, $needle) !== false;
        } else {
            return str_contains($haystack, $needle);
        }
    }
}