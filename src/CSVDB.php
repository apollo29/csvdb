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
     * index column: check if unique?
     * custom unique?
     * auto increment
     */

    public string $file;
    public string $document;
    public string $basedir;
    public CSVConfig $config;
    public string $index;

    private array $select = array();
    private array $where = array();
    private string $operator = CSVDB::AND;
    private array $order = array();
    private int $limit = 0;
    private bool $count = false;

    private Reader $cache;

    const ASC = "asc";
    const DESC = "desc";

    const AND = "and";
    const OR = "or";

    const NEG = true;

    /**
     * @throws \Exception
     */
    public function __construct(string $file, CSVConfig $config = null)
    {
        if (!CSVUtilities::is_csv($file)) {
            throw new \Exception('Unable to open CSV file');
        }

        $this->file = $file;
        $this->basedir = CSVUtilities::csv_dir($file);
        $this->document = CSVUtilities::csv_file($file);
        $this->config = $config ?: CSVConfig::default();

        // cache
        $this->setup_cache();

        // history
        $this->setup_history();

        // setup index
        $this->index = $this->setup_index();
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function writer(string $mode = "a+"): Writer
    {
        $csv = Writer::createFromPath($this->file, $mode);
        $csv->setDelimiter($this->config->delimiter);
        return $csv;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function reader(bool $forced = false): Reader
    {
        if ($forced or !isset($this->cache)) {
            $reader = Reader::createFromPath($this->file);
            $reader->setDelimiter($this->config->delimiter);
            if ($this->config->headers) {
                $reader->setHeaderOffset(0);
            }
            return $reader;
        }
        return $this->cache;
    }

    private function setup_cache(): void
    {
        if ($this->config->cache) {
            $this->cache();
        }
    }

    private function cache(): void
    {
        $this->cache = $this->reader(true);
    }

    public function setup_history(): void
    {
        if ($this->config->history) {
            $dir = $this->history_dir();
            $files = scandir($dir, SCANDIR_SORT_DESCENDING);
            if (is_file($dir . $files[0])) {
                $latest = $files[0];
                if (md5_file($this->file) !== md5_file($dir . $latest)) {
                    $this->history();
                }
            } else {
                $this->history();
            }
        }
    }

    public function history_dir(): string
    {
        $dir = $this->basedir . "/history/";
        if (!file_exists($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    private function history(): void
    {
        $dir = $this->history_dir();
        $time = date_format(new \DateTime(), "YmdHisu");
        $filename = $dir . $time . "_" . $this->document;
        copy($this->file, $filename);
    }

    private function setup_index(): string
    {
        return $this->headers()[$this->config->index];
    }

    // CREATE

    /**
     * @throws CannotInsertRecord
     */
    public function insert(array $data): void
    {
        $writer = $this->writer();
        $data = $this->insert_prepare_stmt($data);
        if (is_array($data[0])) {
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
    }

    private function insert_prepare_stmt(array $data): array
    {
        if ($this->has_headers($this->headers(), $data)) {
            if ($this->has_multiple_records($data)) {
                $records = array();
                foreach ($data as $record) {
                    $records[] = array_values($record);
                }
                return $records;
            }
            return array_values($data);
        } else {
            return $data;
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
            if ($this->has_multiple_records($orderVal)) {
                $order = [$orderVal[$key] => self::ASC];
            } else {
                $key = key($orderVal);
                if (isset($orderVal[0])) {
                    $order = [$orderVal[0] => self::ASC];
                } else {
                    $order = [$key => $orderVal[$key]];
                }
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

    public function count(): CSVDB
    {
        $this->count = true;
        return $this;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function get(Converter $converter = null): array
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

        if ($this->count) {
            // count
            $data = array("count" => $records->count());
        } else {
            // select
            $data = $this->select_stmt($records);
        }

        // converter
        if (isset($converter)) {
            $data = $converter->convert($data);
        }

        // reset
        $this->reset();
        return $data;
    }

    // UPDATE

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function update(array $update = array(), array $where = array()): void
    {
        if (count($update) == 0) {
            throw new \Exception('Nothing to update.');
        }
        if (isset($update[0])) {
            throw new \Exception('Update is not an associative array.');
        }

        $records = $this->select()->get();
        $this->delete_all();

        $data = array();
        foreach ($records as $record) {
            // todo improve with array_diff_assoc
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
            if ($this->has_multiple_records($where)) {
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

    /**
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function upsert(array $update, array $where = array()): void
    {
        if (count($update) == 0) {
            throw new \Exception('Nothing to update/insert.');
        } elseif ($this->has_multiple_records($update)) {
            throw new \Exception('Update/insert only one row.');
        }

        if (count($where) == 0) {
            $index = $this->index();
            $where[$index] = $update[$index];
        }

        $count = $this->select()->count()->where($where)->get();

        if ($count["count"] > 0) {
            $this->update($update, $where);
        } else {
            $this->insert($update);
        }
    }

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
                return $this->delete_where_stmts($row, $where);
            });
            $records = $stmt->process($reader);

            $headers = $reader->getHeader();
            $data = $records->jsonSerialize();

            $writer = $this->writer("w+");
            $writer->insertOne($headers);
            $writer->insertAll($data);
        }

        // cache
        if ($this->config->cache) {
            $this->cache();
        }

        // history
        if ($this->config->history) {
            $this->history();
        }
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
        if ($this->has_multiple_records($where)) {
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

    // UTIL

    private function reset(): void
    {
        $this->select = array();
        $this->where = array();
        $this->operator = CSVDB::AND;
        $this->order = array();
        $this->limit = 0;
        $this->count = false;
    }

    public function headers(): array
    {
        $reader = $this->reader();
        return $reader->getHeader();
    }

    private function has_headers(array $headers, array $update): bool
    {
        $hasHeader = false;
        $record = $update;
        if ($this->has_multiple_records($update)) {
            $key = key($update);
            $record = $update[$key];
        }
        foreach ($headers as $header) {
            if (array_key_exists($header, $record)) {
                $hasHeader = true;
            }
        }
        return $hasHeader;
    }

    private function index(): string
    {
        $headers = $this->headers();
        return $headers[$this->config->index];
    }

    private function has_multiple_records($data): bool
    {
        $key = key($data);
        return is_array($data[$key]);
    }
}