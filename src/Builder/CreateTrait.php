<?php

namespace CSVDB\Builder;

use CSVDB\Helpers\Records;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\UnableToProcessCsv;

trait CreateTrait
{

    /**
     * @param array $data
     * @return array
     * @throws CannotInsertRecord
     * @throws \Exception
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     */
    public function insert(array $data): array
    {
        $writer = $this->writer();

        $this->validate($data);

        if (!$this->check_unique_constraints($data)) {
            throw new CannotInsertRecord("Unique constraints are violated.");
        }

        $data = $this->insert_prepare_stmt($data);

        $amount = 1;
        if (is_array($data[0])) {
            $amount = count($data);
            $writer->insertAll($data);
        } else {
            $writer->insertOne($data);
        }

        // cache
        if ($this->config->cache) {
            $this->cache();
        }

        // history
        if ($this->config->history) {
            $this->history();
        }

        return $this->insert_record($amount);
    }

    /**
     * @throws UnableToProcessCsv
     * @throws InvalidArgument
     * @throws Exception
     */
    private function insert_record(int $amount = 1): array
    {
        $reader = $this->reader();
        $count = $reader->count();
        $last = $count - $amount;
        $records = [];
        for ($i = $last; $i < $count; $i++) {
            $records[] = $reader->fetchOne($i);
        }
        return $records;
    }

    /**
     * @throws \Exception|UnableToProcessCsv
     */
    private function insert_prepare_stmt(array $data): array
    {
        if ($this->config->autoincrement && count($this->headers()) == count($data)) {
            $index = $data[$this->config->index] ?? $data[$this->index];
            if (!is_numeric($index)) {
                throw new \Exception("Error on Insert Statement. Autoincrement is activated but Index Field is filled and not numeric: '$index'");
            }
        }

        if ($this->has_headers($this->headers(), $data)) {
            if (Records::has_multiple_records($data)) {
                $records = array();
                foreach ($data as $record) {
                    $records[] = $this->add_autoincrement_value(array_values($record));
                }
                return $records;
            }
            return $this->add_autoincrement_value(array_values($data));
        } else {
            return $this->add_autoincrement_value($data);
        }
    }

    /**
     * @throws UnableToProcessCsv
     * @throws InvalidArgument
     * @throws Exception
     */
    private function add_autoincrement_value(array $data): array
    {
        if ($this->config->autoincrement && count($this->headers()) != count($data)) {
            $pos = $this->config->index;
            $index = $this->autoincrement_value();
            $data = array_merge(array_slice($data, 0, $pos), array($index), array_slice($data, $pos));
        }
        return $data;
    }

    /**
     * @throws UnableToProcessCsv
     * @throws InvalidArgument
     * @throws Exception
     * @throws \Exception
     */
    private function autoincrement_value(): int
    {
        $reader = $this->reader();
        $count = $reader->count();
        if ($count > 0) {
            $last = $count - 1;
            $record = $reader->fetchOne($last);
            $index = $record[$this->index];
            if (is_numeric($index)) {
                return intval($index) + 1;
            } else {
                throw new \Exception("There is an error with your CSV file. Autoincrement is activated but Index field is not numeric.");
            }
        }
        return 1;
    }

}