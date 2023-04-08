<?php

namespace CSVDB\Schema;

use CSVDB\Enums\DatatypeEnum;
use CSVDB\Enums\IndexEnum;
use CSVDB\Enums\SchemaEnum;
use CSVDB\Helpers\Records;

class SchemaValidator
{
    public array $schema;
    public bool $strict;

    /**
     * @param array $schema
     * @throws \Exception
     */
    public function __construct(array $schema, bool $strict = false)
    {
        $this->schema = $this->validate_schema($schema);
        $this->strict = $strict;
    }

    /**
     * @throws \Exception
     */
    private function validate_schema(array $schema): array
    {
        if (empty($schema)) {
            throw new \Exception("Schema is empty and therefore not valid.");
        }
        if (!Records::is_assoc($schema)) {
            throw new \Exception("Schema is a non associative Records and therefore not valid.");
        }
        foreach ($schema as $field => $model) {
            if (array_key_exists(SchemaEnum::TYPE, $model)) {
                $valid = DatatypeEnum::isValid($model[SchemaEnum::TYPE]);
                if (!$valid) {
                    throw new \Exception("Schema is not valid. Wrong Type for $field: " . json_encode($model));
                }
            } else {
                throw new \Exception("Schema is not valid. Type missing for $field: " . json_encode($model));
            }

            if (array_key_exists(SchemaEnum::INDEX, $model)) {
                $valid = IndexEnum::isValid($model[SchemaEnum::INDEX]);
                if (!$valid) {
                    throw new \Exception("Schema is not valid. Wrong Index for $field: " . json_encode($model));
                }
            }
        }
        return $schema;
    }

    /**
     * @throws \Exception
     */
    public function validate(array $record): bool
    {
        if (Records::has_multiple_records($record)) {
            $valid = true;
            foreach ($record as $data) {
                if (!$this->validate($data)) {
                    $valid = false;
                }
            }
            return $valid;
        } else {
            return $this->validate_record($record);
        }
    }

    /**
     * @throws \Exception
     */
    private function validate_record(array $record): bool
    {
        if (!Records::is_assoc($record) && $this->strict) {
            throw new \Exception("Schema Validation is strict, non associative Records are not allowed.");
        } else if (!Records::is_assoc($record)) {
            return $this->validate_simple_record($record);
        }

        // todo what when no type? what when no schema?? exception??
        $valid = true;
        foreach ($record as $key => $value) {
            if (array_key_exists($key, $this->schema)) {
                $type = $this->schema[$key]["type"];
                $value_type = DatatypeEnum::getValidTypeFromSample($value);
                if (is_string($value_type)) {
                    if ($value_type !== $type) {
                        $valid = false;
                    }
                }
            } else if ($this->strict) {
                return false;
            }
        }
        return $valid;
    }

    private function validate_simple_record(array $record): bool
    {
        $valid = true;
        $schema = array_values($this->schema);
        for ($i = 0; $i < count($record); $i++) {
            $type = $schema[$i]["type"];
            $value_type = DatatypeEnum::getValidTypeFromSample($record[$i]);
            if (is_string($value_type)) {
                if ($value_type !== $type) {
                    $valid = false;
                }
            }
        }
        return $valid;
    }

    // INDEX

    public function indexes(): array
    {
        return array_filter($this->schema, function ($value) {
            return array_key_exists(SchemaEnum::INDEX, $value);
        });
    }
}