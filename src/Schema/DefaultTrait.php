<?php

namespace CSVDB\Schema;

use CSVDB\CSVDB;
use CSVDB\Enums\SchemaEnum;
use CSVDB\Helpers\Records;

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

    private function prepare_default(array $record, bool $update = false): array
    {
        if (!Records::is_assoc($record) && count($record) !== count($this->schema->schema)) {
            throw new \Exception("Record is not an associative array and some Fields are missing. Please provide CSVDB::EMPTY for all Fiels with Default values");
        }

        if (Records::has_multiple_records($record)) {
            $records = [];
            foreach ($record as $data) {
                $records[] = $this->prepare_default_stmt($data, $update);
            }
            return $records;
        }
        return $this->prepare_default_stmt($record, $update);
    }

    private function prepare_default_stmt(array $record, bool $update = false): array
    {
        if ($this->has_schema()) {
            if (Records::is_assoc($record)) {
                $defaults = $this->schema->defaults();
                foreach ($defaults as $key => $default) {
                    if (array_key_exists($key, $record)) {
                        $value = $record[$key];
                        if (empty($value) || $value === CSVDB::EMPTY) {
                            $record[$key] = $this->default($key);
                        }
                    } else if (!$update) {
                        $record[$key] = $this->default($key);
                    }
                }
            } else {
                return $this->prepare_simple_default_stmt($record);
            }
        }
        return $record;
    }

    private function prepare_simple_default_stmt(array $record): array
    {
        if ($this->has_schema()) {
            $schema = $this->schema->schema;
            $i = 0;
            while ($schema_value = current($schema)) {
                if (array_key_exists(SchemaEnum::DEFAULT, $schema_value)) {
                    $value = $record[$i];
                    $key = key($schema);
                    if (empty($value) || $value === CSVDB::EMPTY) {
                        $record[$i] = $this->default($key);
                    }
                }
                $i++;
                next($schema);
            }
        }
        return $record;
    }
}