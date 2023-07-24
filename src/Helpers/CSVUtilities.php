<?php

namespace CSVDB\Helpers;

class CSVUtilities
{

    public static function is_csv($document): bool
    {
        if (is_file($document)) {
            $path_parts = pathinfo($document);
            return strtolower($path_parts['extension']) == "csv";
        }
        return false;
    }

    public static function csv_file($document): ?string
    {
        if (is_file($document)) {
            $path_parts = pathinfo($document);
            return $path_parts['filename'] . "." . $path_parts['extension'];
        }
        return null;
    }

    public static function csv_dir($document): ?string
    {
        if (is_file($document)) {
            $path_parts = pathinfo($document);
            return $path_parts['dirname'];
        }
        return null;
    }

    public static function csv_database($document): ?string
    {
        if (is_file($document)) {
            $path_parts = pathinfo($document);
            return $path_parts['filename'];
        }
        return null;
    }
}