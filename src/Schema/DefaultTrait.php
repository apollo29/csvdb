<?php

namespace CSVDB\Schema;

use CSVDB\Enums\DatatypeEnum;

trait DefaultTrait
{
    private function has_default(string $key): bool
    {
        return $this->schema->has_default($key);
    }

    private function default(string $key)
    {
        $default = $this->schema->default($key);
        $functions = get_class_methods($this->schema->functions);
        if (in_array($default, $functions)) {
            return $this->schema->functions->{$default}();
        }
        return $default;
    }
}