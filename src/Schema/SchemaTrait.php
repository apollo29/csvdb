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

    /**
     * @throws \Exception
     */
    private function check_autoincrement(string $key): void
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
    private function check_primarykey(string $key): void
    {
        if ($this->index !== $key) {
            throw new \Exception("Schema inconsistency. PRIMARY_KEY is set for Field $key, but Index is set to " . $this->index);
        }
        $this->check_constraint($key);
    }

    private function check_constraint(string $key): void
    {
        if (!array_key_exists($key, $this->constraints)) {
            $this->unique($key);
        }
    }

    public function has_schema(): bool
    {
        return isset($this->schema);
    }

    /**
     * @throws \Exception
     */
    private function validate(array $record, bool $update = false): void
    {
        if ($this->has_schema()) {
            $this->schema->validate($record, $update);
        }
    }
}