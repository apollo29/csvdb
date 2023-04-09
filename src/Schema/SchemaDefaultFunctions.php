<?php

namespace CSVDB\Schema;

class SchemaDefaultFunctions
{
    public static function current_timestamp(): int
    {
        return time();
    }
}