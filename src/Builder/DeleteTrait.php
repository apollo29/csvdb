<?php

namespace CSVDB\Builder;

use CSVDB\Helpers\Records;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Statement;

trait DeleteTrait
{

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function delete(array $where = array()): bool
    {
        $reader = $this->reader();
        if (count($where) == 0) {
            $this->delete_all();
            $count = $reader->count();
        } else {
            $stmt = Statement::create()->where(function ($row) use ($where) {
                return $this->delete_where_stmts($row, $where);
            });
            $records = $stmt->process($reader);

            $headers = $reader->getHeader();
            $data = $records->jsonSerialize();

            $writer = $this->writer("w+");
            $writer->insertOne($headers);
            $writer->insertAll($data);

            $count = $this->select()->count()->where($where)->get()['count'];
        }

        // cache
        if ($this->config->cache) {
            $this->cache();
        }

        // history
        if ($this->config->history) {
            $this->history();
        }

        return $count == 0;
    }

    /**
     * Where Statement for delete;
     * The mechanism works the other way around. We look for all records, not fulfill the where clause and then store
     * these records without the "deleted" ones.
     *
     * @param array $record
     * @param array $where
     * @return bool
     */
    private function delete_where_stmts(array $record, array $where): bool
    {
        if (Records::has_multiple_records($where)) {
            $complies = false;
            foreach ($where as $check) {
                if ($this->delete_where_stmt($record, $check)) {
                    $complies = true;
                }
            }
            return $complies;
        } else {
            return $this->delete_where_stmt($record, $where);
        }
    }

    private function delete_where_stmt(array $record, array $where): bool
    {
        $complies = false;
        $key = key($where);
        $value = $where[$key];
        if (array_key_exists($key, $record)) {
            $complies = ($record[$key] != $value);
        }
        return $complies;
    }

    /**
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws Exception
     */
    private function delete_all(): void
    {
        $headers = $this->headers();
        $writer = $this->writer("w+");
        $writer->insertOne($headers);
    }

}