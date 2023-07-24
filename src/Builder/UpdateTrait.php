<?php

namespace CSVDB\Builder;

use CSVDB\Helpers\Records;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\UnableToProcessCsv;

trait UpdateTrait
{

    /**
     * @param array $update
     * @param array $where
     * @return array
     * @throws CannotInsertRecord
     * @throws Exception
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     * @throws \Exception
     */
    public function update(array $update = array(), array $where = array()): array
    {
        if (count($update) == 0) {
            throw new \Exception('Nothing to update.');
        }
        if (!Records::is_assoc($update)) {
            throw new \Exception('Update is not an associative array.');
        }

        $this->validate($update, true);

        $update = $this->prepare_default($update, true);

        if (!$this->check_unique_constraints_update($update, $where)) {
            throw new \Exception("Unique constraints are violated.");
        }

        $records = $this->select()->get();
        $this->delete_all();

        $data = array();
        foreach ($records as $record) {
            // todo improve with array_diff_assoc
            $data[] = $this->update_stmts($record, $update, $where);
        }

        $result = array_udiff_assoc($data, $records, function ($a, $b) {
            return array_diff_assoc($a, $b);
        });
        if (count($data) > 0) {
            $writer = $this->writer();
            if (count($data) > 1) {
                $writer->insertAll($data);
            } else {
                $writer->insertOne($data);
            }
        }
        return $result;
    }

    private function update_stmts(array $record, array $update, array $where): array
    {
        if (count($where) == 0) {
            return $this->update_stmt($record, $update);
        } else {
            if (Records::has_multiple_records($where)) {
                $complies = true;
                foreach ($where as $check) {
                    if (!$this->check_update_stmt($record, $check)) {
                        $complies = false;
                    }
                }

                if ($complies) {
                    return $this->update_stmt($record, $update);
                }
            } else {
                if ($this->check_update_stmt($record, $where)) {
                    return $this->update_stmt($record, $update);
                }
            }
            return $record;
        }
    }

    private function check_update_stmt(array $record, $where): bool
    {
        $key = key($where);
        $value = $where[$key];
        return $record[$key] == $value;
    }

    // todo where stmts!!! -> own trait
    private function update_stmt(array $record, array $update): array
    {
        foreach ($update as $key => $value) {
            $record[$key] = $value;
        }
        return $record;
    }

    /**
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws Exception|UnableToProcessCsv
     * @throws \Exception
     */
    public function upsert(array $update, array $where = array()): array
    {
        if (count($update) == 0) {
            throw new \Exception('Nothing to update/insert.');
        } elseif (Records::has_multiple_records($update)) {
            throw new \Exception('Update/insert only one row.');
        }

        $is_update = false;

        if (count($where) == 0) {
            $index = $this->index();
            if (array_key_exists($index, $update)) {
                $where[$index] = $update[$index];
                $count = $this->select()->count()->where($where)->get();
                $is_update = ($count['count'] > 0);
            }
        } else {
            $count = $this->select()->count()->where($where)->get();
            $is_update = ($count['count'] > 0);
        }

        if ($is_update) {
            $result = $this->update($update, $where);
        } else {
            $result = $this->insert($update);
        }

        return $result;
    }

}