<?php

namespace CSVDB\Schema;

abstract class DefaultFunctions
{
    public function current_timestamp(): int
    {
        return time();
    }
}