<?php

namespace CSVDB\Schema;

use CSVDB\Enums\ConstraintEnum;
use CSVDB\Enums\SchemaEnum;

trait SchemaTrait
{

    /**
     * @throws \Exception
     */
    public function schema(array $schema, bool $strict = false, DefaultFunctions $functions = null): void
    {
        $this->schema = new Schema($schema, $strict, $functions);
        $constraints = $this->schema->constraints();
        foreach ($constraints as $key => $constraint) {
            if ($constraint[SchemaEnum::CONSTRAINT] === ConstraintEnum::AUTO_INCREMENT) {
                $this->check_autoincrement($key);
            } else if ($constraint[SchemaEnum::CONSTRAINT] === ConstraintEnum::PRIMARY_KEY) {
                $this->check_primarykey($key);
            } else if ($constraint[SchemaEnum::CONSTRAINT] === ConstraintEnum::UNIQUE) {
                $this->check_constraint($key);
            }
        }
    }

    public function getSchema(): array
    {
        // todo nullable
        $structure = array();
        $config = $this->config;
        $constraints = $this->constraints;
        if ($this->has_schema()) {
            $schema = $this->schema->schema;
            $index = array_keys($schema)[$config->index];
            foreach ($schema as $field => $item) {
                if ($field == $index && $config->autoincrement) {
                    $item["extra"] = ConstraintEnum::AUTO_INCREMENT;
                }
                if (!empty($constraints[$field])) {
                    if (empty($item["constraint"])) {
                        $item["constraint"]=ConstraintEnum::UNIQUE;
                    }
                }
                $item["encoding"] = $config->encoding;
                $structure[$field] = $item;
            }
        } else {
            $types = $this->getDatatypes();
            $index = array_keys($types)[$config->index];
            foreach ($types as $field => $type) {
                $constraint = "";
                $extra = "";
                if ($field == $index) {
                    $constraint = ConstraintEnum::PRIMARY_KEY;
                    if ($config->autoincrement) {
                        $extra = ConstraintEnum::AUTO_INCREMENT;
                    }
                }
                if (array_key_exists($field, $constraints)) {
                    $constraint = ConstraintEnum::UNIQUE;
                }
                $structure[$field] = [
                    "type" => $type,
                    "encoding" => $config->encoding
                ];
                if (!empty($constraint)) {
                    $structure[$field]["constraint"]=$constraint;
                }
                if (!empty($extra)) {
                    $structure[$field]["extra"]=$extra;
                }
            }
        }
        return $structure;
    }

    /**
     * @throws \Exception
     */
    private
    function check_autoincrement(string $key): void
    {
        if (!$this->config->autoincrement) {
            throw new \Exception("Schema inconsistency. AUTO_INCREMENT is set for Field $key, but AUTO_INCREMENT is not configured within Config.");
        }
        if ($this->index !== $key) {
            throw new \Exception("Schema inconsistency. AUTO_INCREMENT is set for Field $key, but Index is set to " . $this->index);
        }
        $this->check_constraint($key);
    }

    /**
     * @throws \Exception
     */
    private
    function check_primarykey(string $key): void
    {
        if ($this->index !== $key) {
            throw new \Exception("Schema inconsistency. PRIMARY_KEY is set for Field $key, but Index is set to " . $this->index);
        }
        $this->check_constraint($key);
    }

    private
    function check_constraint(string $key): void
    {
        if (!array_key_exists($key, $this->constraints)) {
            $this->unique($key);
        }
    }

    public
    function has_schema(): bool
    {
        return isset($this->schema);
    }

    /**
     * @throws \Exception
     */
    private
    function validate(array $record, bool $update = false): void
    {
        if ($this->has_schema()) {
            $this->schema->validate($record, $update);
        }
    }
}