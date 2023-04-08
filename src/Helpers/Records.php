<?php

namespace CSVDB\Helpers;

class Records
{
    public static function has_multiple_records(array $data): bool
    {
        $key = key($data);
        return is_array($data[$key]);
    }

    public static function is_assoc(array $data): bool {
        return !isset($data[0]);
    }
}