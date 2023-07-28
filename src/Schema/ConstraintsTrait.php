<?php

namespace CSVDB\Schema;

use CSVDB\CSVDB;
use CSVDB\Enums\SchemaEnum;
use CSVDB\Helpers\Records;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;

trait ConstraintsTrait
{

    public function constraints(): array
    {
        $constraints = $this->constraints;
        if ($this->has_schema()) {
            return $this->schema->constraints();
        } else {
            $schema_constraints = $this->get_constraints($this->getSchema());
            $constraints = array_merge($constraints, $schema_constraints);
        }
        return $constraints;
    }

    private function get_constraints(array $schema): array
    {
        return array_filter($schema, function ($value) {
            return array_key_exists(SchemaEnum::CONSTRAINT, $value);
        });
    }

    /**
     * @throws CannotInsertRecord
     */
    private function check_unique_constraints(array $data): bool
    {
        if (count($this->constraints) > 0) {
            $constraints = array_values($this->constraints);
            if (Records::has_multiple_records($data)) {
                $check = true;
                foreach ($data as $record) {
                    if (!$this->check_unique_constraints_record($record, $constraints)) {
                        $check = false;
                    }
                }
                return $check;
            }
            return $this->check_unique_constraints_record($data, $constraints);
        }
        return true;
    }

    /**
     * @throws CannotInsertRecord
     */
    private function check_unique_constraints_record(array $record, array $constraints): bool
    {
        if (isset($record[0])) {
            throw new CannotInsertRecord("Your data is not an associative array and there are unique constraints.");
        }

        if ($this->check_unique_constraints_missing($record, $constraints)) {
            $where = $this->prepare_unique_stmt($record, $constraints);
            $count = $this->select()->count()->where($where, CSVDB::OR)->get();
            return $count['count'] == 0;
        }
        return false;
    }

    private function check_unique_constraints_update(array $data, array $where): bool
    {
        if (count($this->constraints) > 0) {
            $constraints = array_values($this->constraints);
            // if no constraints available, all good
            if ($this->check_unique_constraints_available($data, $constraints)) {
                // constraints are available
                // if no where stmt available, not good
                if (count($where) > 0) {
                    // check for other records with constraints present, if there are none, all good
                    $unique_where = $this->prepare_unique_stmt($data, $constraints);
                    $records = $this->select()->where($unique_where, CSVDB::OR)->get();
                    if (count($records) > 0) {
                        // if there are records with constraints present, check if records are identical
                        $self = $this->select()->where($where)->get();
                        $index = $this->index;
                        $check = true;
                        foreach ($records as $record) {
                            if ($record[$index] != $self[0][$index]) {
                                $check = false;
                            }
                        }
                        return $check;
                    }
                }
            }
        }
        return true;
    }

    private function check_unique_constraints_missing(array $data, array $constraints): bool
    {
        $available = true;
        foreach ($constraints as $constraint) {
            if (!array_key_exists($constraint, $data)) {
                if ($constraint !== $this->index) {
                    $available = false;
                }
            }
        }
        return $available;
    }

    private function check_unique_constraints_available(array $data, array $constraints): bool
    {
        $available = false;
        foreach ($constraints as $constraint) {
            if (array_key_exists($constraint, $data)) {
                $available = true;
            }
        }
        return $available;
    }

    private function prepare_unique_stmt(array $data, array $constraints): array
    {
        $where = [];
        foreach ($constraints as $constraint) {
            if (array_key_exists($constraint, $data)) {
                $where[] = [$constraint => $data[$constraint]];
            }
        }

        if (count($where) == 1) {
            return $where[0];
        }
        return $where;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function unique_index(): void
    {
        $this->unique($this->index);
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function unique(string ...$constraint): void
    {
        $headers = $this->headers();
        foreach ($constraint as $value) {
            if (in_array($value, $headers)) {
                $this->constraints[$value] = $value;
            }
        }
    }

}