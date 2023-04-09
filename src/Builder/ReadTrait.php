<?php

namespace CSVDB\Builder;

use CSVDB\CSVDB;
use CSVDB\Helpers\Str;

trait ReadTrait
{

    private function where_stmt(array $row): bool
    {
        $where_array = $this->where;
        if (count($where_array) > 1) {
            $operator = $this->operator;
            $last = array_key_last($where_array);
            if ($where_array[$last] === self::AND || $where_array[$last] === self::OR) {
                $operator = $where_array[$last];
                unset($where_array[$last]);
            }

            $return = null;
            foreach ($where_array as $where) {
                if (count($where) > 1) {
                    $last = array_key_last($where);
                    $custom_operator = $where[$last];
                    foreach ($where as $multiple) {
                        if (is_array($multiple)) {
                            $return = $this->create_where_stmts($return, $row, $multiple, $custom_operator);
                        }
                    }
                } else {
                    $return = $this->create_where_stmts($return, $row, $where, $operator);
                }
            }
            return $return;
        } else {
            return $this->create_where_stmt($row, $where_array);
        }
    }

    private function create_where_stmts(?bool $return, array $row, array $where, string $operator): bool
    {
        if (is_bool($return)) {
            if ($operator == CSVDB::AND) {
                return $return && $this->create_where_stmt($row, $where);
            } else {
                return $return || $this->create_where_stmt($row, $where);
            }
        } else {
            return $this->create_where_stmt($row, $where);
        }
    }

    private function create_where_stmt(array $row, array $where): bool
    {
        $key = key($where);
        $value = $where[$key];

        if (is_array($value)) {
            return $this->where_stmt_array($row[$key], $value);
        } else if (empty($value) || $value === self::EMPTY) {
            return empty($row[$key]);
        }
        return $row[$key] === $value;
    }

    private function where_stmt_array(string $record, array $value): bool
    {
        if (empty($value[1]) || $value[1] === self::EMPTY) {
            return $this->where_is_empty($record);
        } else if ($value[1] === self::LIKE) {
            return $this->where_is_like($record, $value[0]);
        } else if ($value[1] === self::NEG) {
            return $this->where_is_negative($record, $value[0]);
        } else {
            return $this->where_is_array($record, $value);
        }
    }

    private function where_is_empty(string $record): bool
    {
        return empty($record);
    }

    private function where_is_like(string $record, $value): bool
    {
        if (is_array($value)) {
            $return = false;
            foreach ($value as $val) {
                if ($this->where_is_like_stmt($record, $val)) {
                    $return = true;
                }
                /*
                if (Str::contains($record, $val)) {
                    $return = true;
                }
                */
            }
            return $return;
        }
        return $this->where_is_like_stmt($record, $value);
    }

    private function where_is_like_stmt(string $record, $value): bool
    {
        if (is_string($value)) {
            $first_char = substr($value, 0, 1);
            $last_char = substr($value, -1);
            if ($first_char === "%" && $last_char !== "%") {
                return Str::starts_with($record, substr($value, 1));
            } else if ($first_char !== "%" && $last_char === "%") {
                return Str::ends_with($record, substr($value, 0, -1));
            } else {
                $val = $value;
                if ($first_char === "%" && $last_char === "%") {
                    $val = substr($value, 1, -1);
                }
                return Str::contains($record, $val);
            }
        }
        return false;
    }

    private function where_is_negative(string $record, $value): bool
    {
        if (empty($value) || $value === self::EMPTY) {
            return !$this->where_is_empty($record);
        } else if (is_array($value)) {
            return !$this->where_stmt_array($record, $value);
        }
        return $record !== $value;
    }

    private function where_is_array(string $record, array $value): bool
    {
        return in_array($record, $value);
    }

    private function order_stmt(array $row1, array $row2): int
    {
        $return = 0;
        if (count($this->order) > 1) {
            foreach ($this->order as $order) {
                $return = $return && $this->create_order_stmt($row1, $row2, $order);
            }
        } else {
            $return = $this->create_order_stmt($row1, $row2, $this->order);
        }
        return $return;
    }

    private function create_order_stmt(array $row1, array $row2, array $order): int
    {
        $key = key($order);
        if ($order[$key] == CSVDB::DESC) {
            return strcmp($row2[$key], $row1[$key]);
        }
        return strcmp($row1[$key], $row2[$key]);
    }
}