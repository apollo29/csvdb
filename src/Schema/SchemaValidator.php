<?php

namespace CSVDB\Schema;

use CSVDB\Enums\ConstraintEnum;
use CSVDB\Enums\DatatypeEnum;
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

            if (array_key_exists(SchemaEnum::CONSTRAINT, $model)) {
                if (is_string($model[SchemaEnum::CONSTRAINT])) {
                    $this->validate_constraint($field, $model[SchemaEnum::CONSTRAINT]);
                } elseif (is_array($model[SchemaEnum::CONSTRAINT])) {
                    foreach ($model[SchemaEnum::CONSTRAINT] as $constraint) {
                        $this->validate_constraint($field, $constraint);
                    }
                }
            }
        }
        return $schema;
    }

    /**
     * @throws \Exception
     */
    private function validate_constraint(string $field, string $constraint): void
    {
        try {
            $valid = ConstraintEnum::isValid($constraint);
            if (!$valid) {
                throw new \Exception("Schema is not valid. Wrong Constraint for $field: " . $constraint);
            }
        } catch (\ReflectionException $e) {
            throw new \Exception("Schema is not valid. Wrong Constraint for $field: " . $constraint);
        }
    }

    /**
     * @throws \Exception
     */
    public function validate(array $record, bool $update = false): bool
    {
        if (Records::has_multiple_records($record)) {
            foreach ($record as $data) {
                $this->validate($data, $update);
            }
        } else {
            $this->validate_record($record, $update);
        }
        return true;
    }

    /**
     * @throws \Exception
     */
    private function validate_record(array $record, bool $update = false): void
    {
        if (!Records::is_assoc($record) && $this->strict) {
            throw new \Exception("Schema Validation is strict, non associative Records are not allowed.");
        } else if (!Records::is_assoc($record)) {
            $this->validate_simple_record($record);
        }

        if ($this->strict) {
            $schema_keys = array_keys($this->schema);
            $record_keys = array_keys($record);
            $diff_schema = array_diff($schema_keys, $record_keys);
            $diff_record = array_diff($record_keys, $schema_keys);
            if (count($diff_schema) !== 0 && !$update) {
                throw new \Exception("Schema Validation is strict. Field(s) " . json_encode(array_values($diff_schema)) . " in Record is/are missing.");
            }
            if (count($diff_record) !== 0) {
                throw new \Exception("Schema Validation is strict. Field(s) " . json_encode(array_values($diff_record)) . " in Record is/are missing in schema.");
            }
        }

        foreach ($record as $key => $value) {
            if (array_key_exists($key, $this->schema)) {
                // type
                if (!empty($value)) {
                    $type = $this->schema[$key][SchemaEnum::TYPE];
                    $value_type = DatatypeEnum::getValidTypeFromSample($value);
                    if (is_string($value_type)) {
                        if ($value_type !== $type) {
                            throw new \Exception("Schema is violated: Expected Type $type, but Type is $value_type");
                        }
                    }
                }

                if (array_key_exists(SchemaEnum::CONSTRAINT, $this->schema[$key])) {
                    if (empty($value)) {
                        throw new \Exception("Schema is violated: Value is empty, but has Constraint: " . json_encode($this->schema[$key][SchemaEnum::CONSTRAINT]));
                    }
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    private function validate_simple_record(array $record): void
    {
        $schema = array_values($this->schema);
        for ($i = 0; $i < count($record); $i++) {
            $type = $schema[$i]["type"];
            $value_type = DatatypeEnum::getValidTypeFromSample($record[$i]);
            if (is_string($value_type)) {
                if ($value_type !== $type) {
                    throw new \Exception("Schema is violated: Expected Type $type, but Type is $value_type");
                }
            }
        }
    }

    // CONSTAINT

    public function constraints(): array
    {
        return array_filter($this->schema, function ($value) {
            return array_key_exists(SchemaEnum::CONSTRAINT, $value);
        });
    }
}