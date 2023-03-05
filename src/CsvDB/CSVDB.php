<?php

namespace CSVDB;

use CSVDB\Helpers\CSVConfig;
use CSVDB\Helpers\CSVUtilities;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\TabularDataReader;
use League\Csv\Writer;

class CSVDB
{
    /*
     * todo
     * select, where, delete, orderBy = check if header exist?!
     * delete return?
     * insert return?
     * update return?
     */

    public string $document;
    public CSVConfig $config;

    private array $select = array();
    private array $where = array();
    private string $operator = CSVDB::AND;
    private array $order = array();
    private int $limit = 0;

    const ASC = "asc";
    const DESC = "desc";

    const AND = "and";
    const OR = "or";

    const NEG = true;

    /**
     * @throws \Exception
     */
    public function __construct(string $document, CSVConfig $config = null)
    {
        if (!CSVUtilities::is_csv($document)) {
            throw new \Exception('Unable to open CSV file');
        }

        $this->config = $config ?: CSVConfig::default();
        $this->document = $document;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function writer(string $mode = "a+"): Writer
    {
        $csv = Writer::createFromPath($this->document, $mode);
        $csv->setDelimiter($this->config->delimiter);
        return $csv;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function reader(): Reader
    {
        $reader = Reader::createFromPath($this->document);
        $reader->setDelimiter($this->config->delimiter);
        if ($this->config->headers) {
            $reader->setHeaderOffset(0);
        }
        return $reader;
    }

    private function reset(): void
    {
        $this->select = array();
        $this->where = array();
        $this->operator = CSVDB::AND;
        $this->order = array();
        $this->limit = 0;
    }

    private function headers(): array
    {
        $reader = $this->reader();
        return $reader->getHeader();
    }

    // CREATE

    /**
     * @throws CannotInsertRecord
     */
    public function insert(array $data): void
    {
        $writer = $this->writer();
        if (is_array($data[0])) {
            $writer->insertAll($data);
        } else {
            $writer->insertOne($data);
        }
    }

    // READ

    public function select(array $select = array()): CSVDB
    {
        $this->select = $select;
        return $this;
    }

    private function select_stmt(TabularDataReader $records): array
    {
        if (count($this->select) > 1) {
            $data = array();
            foreach ($records as $row) {
                $data[] = $this->create_select_stmt($row);
            }
            return $data;
        }
        return $records->jsonSerialize();
    }

    private function create_select_stmt(array $row): array
    {
        return array_filter($row, function ($k) {
            $valid = $k == $this->select[0];
            if (count($this->select) > 1) {
                for ($i = 1; $i < count($this->select); $i++) {
                    $valid = $valid || $k == $this->select[$i];
                }
            }
            return $valid;
        }, ARRAY_FILTER_USE_KEY);
    }

    public function where(array $where = array(), string $operator = CSVDB::AND): CSVDB
    {
        $this->where = $where;
        $this->operator = $operator;
        return $this;
    }

    private function where_stmt(array $row, array $where_array = array()): bool
    {
        if (count($where_array) == 0) {
            $where_array = $this->where;
        }
        if (count($where_array) > 1) {
            $return = null;
            foreach ($where_array as $where) {
                $return = $this->create_where_stmts($return, $row, $where, $this->operator);
            }
            return $return;
        } else {
            return $this->create_where_stmt($row, $where_array);
        }
    }

    private function create_where_stmts(?bool $return, array $row, array $where, string $operator)
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
            if ($value[1]) {
                return is_bool(strpos($row[$key], $value[0]));
            }
            return strpos($row[$key], $value[0]) !== false;
        }
        return strpos($row[$key], $value) !== false;
    }

    public function orderBy($orderVal = array()): CSVDB
    {
        if (is_array($orderVal)) {
            $key = key($orderVal);
            if (is_numeric($key)) {
                $order = [$orderVal[$key] => self::ASC];
            } else {
                $order = $orderVal;
            }
        } else {
            $order = [$orderVal => self::ASC];
        }

        $this->order = $order;
        return $this;
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

    public function limit(int $limit = 0): CSVDB
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function get(): array
    {
        $reader = $this->reader();
        $stmt = Statement::create();
        // where
        if (count($this->where) > 0) {
            $stmt = $stmt->where(function ($row) {
                return $this->where_stmt($row);
            });
        }
        // order by
        if (count($this->order) > 0) {
            $stmt = $stmt->orderBy(function ($row1, $row2) {
                return $this->order_stmt($row1, $row2);
            });
        }
        // limit
        if ($this->limit > 0) {
            $stmt = $stmt->limit($this->limit);
        }

        $records = $stmt->process($reader);

        // select
        $data = $this->select_stmt($records);

        // reset
        $this->reset();
        return $data;
    }

    // UPDATE

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function update(array $update, array $where = array()): void
    {
        if (count($update) == 0) {
            throw new \Exception('Nothing to update.');
        }

        $records = $this->select()->get();
        $this->delete_all();

        $data = array();
        foreach ($records as $record) {
            $data[] = $this->update_stmts($record, $update, $where);
        }

        if (count($data) > 0) {
            $this->insert($data);
        }
    }

    private function update_stmts(array $record, array $update, array $where): array
    {
        if (count($where) == 0) {
            return $this->update_stmt($record, $update);
        } else {
            $key = key($where);
            $value = $where[$key];
            if (is_array($value)) {
                foreach ($where as $check) {
                    if ($this->check_update_stmt($record, $check)) {
                        return $this->update_stmt($record, $update);
                    }
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

    private function update_stmt(array $record, array $update): array
    {
        foreach ($update as $key => $value) {
            $record[$key] = $value;
        }
        return $record;
    }

    // todo upsert

    // DELETE

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function delete(array $where = array()): void
    {
        if (count($where) == 0) {
            $this->delete_all();
        } else {
            $reader = $this->reader();
            $stmt = Statement::create()->where(function ($row) use ($where) {
                return $this->where_stmt($row, $this->delete_where_stmt($where));
            });
            $records = $stmt->process($reader);

            $headers = $reader->getHeader();
            $data = $records->jsonSerialize();

            $writer = $this->writer("w+");
            $writer->insertOne($headers);
            $writer->insertAll($data);
        }
    }

    private function delete_where_stmt(array $where): array
    {
        $key = key($where);
        $value = $where[$key];
        if (is_array($value)) {
            return [$key => $value[0]];
        }
        return [$key => [$value, CSVDB::NEG]];
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