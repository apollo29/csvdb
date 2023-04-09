<?php

namespace CSVDB;

use CSVDB\Enums\ConstraintEnum;
use CSVDB\Enums\SchemaEnum;
use CSVDB\Helpers\CSVConfig;
use CSVDB\Helpers\CSVUtilities;
use CSVDB\Helpers\DatatypeTrait;
use CSVDB\Helpers\Records;
use CSVDB\Helpers\Str;
use CSVDB\Schema\SchemaValidator;
use DateTime;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\UnableToProcessCsv;
use League\Csv\Writer;

class CSVDB implements Builder\Statement
{
    use DatatypeTrait;

    public string $file;
    public string $document;
    public string $basedir;
    public CSVConfig $config;
    public ?SchemaValidator $schema;
    public string $index;

    private array $select = array();
    private array $where = array();
    private string $operator = CSVDB::AND;
    private array $order = array();
    private int $limit = 0;
    private bool $count = false;

    private Reader $cache;

    private array $constraints = array();

    const ASC = "asc";
    const DESC = "desc";

    const AND = "and";
    const OR = "or";

    const NEG = "[CSVDB::NEG]";
    const LIKE = "[CSVDB::LIKE]";
    const EMPTY = "[CSVDB::EMPTY]";

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

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function setup_cache(): void
    {
        if ($this->config->cache) {
            $this->cache();
        }
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
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
        $time = date_format(new DateTime(), "YmdHisu");
        $filename = $dir . $time . "_" . $this->document;
        copy($this->file, $filename);
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function setup_index(): string
    {
        return $this->headers()[$this->config->index];
    }

    // CONSTRAINTS

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

    // SCHEMA

    /**
     * @throws \Exception
     */
    public function schema(array $schema, bool $strict = false): void
    {
        $this->schema = new SchemaValidator($schema, $strict);
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
    }

    /**
     * @throws \Exception
     */
    private function check_primarykey(string $key): void
    {
        if ($this->index !== $key) {
            throw new \Exception("Schema inconsistency. PRIMARY_KEY is set for Field $key, but Index is set to " . $this->index);
        }
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

    // CREATE

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

    // READ

    public function select(array $select = array()): Builder\Statement
    {
        $this->select = $select;
        return $this;
    }

    public function count(): Builder\Statement
    {
        $this->count = true;
        return $this;
    }

    public function where(array $where = array(), string $operator = CSVDB::AND): Builder\Statement
    {
        $this->where = $where;
        $this->operator = $operator;
        return $this;
    }

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
                if (Str::contains($record, $val)) {
                    $return = true;
                }
            }
            return $return;
        }
        return Str::contains($record, $value);
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

    public function orderBy($orderVal = array()): Builder\Statement
    {
        if (is_array($orderVal)) {
            $key = key($orderVal);
            if (Records::has_multiple_records($orderVal)) {
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

    public function limit(int $limit = 0): Builder\Statement
    {
        $this->limit = $limit;
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

        // select
        if (count($this->select) > 0) {
            $records = $stmt->process($reader, $this->select);
        } else {
            $records = $stmt->process($reader);
        }

        if ($this->count) {
            // count
            $data = array("count" => $records->count());
        }

        // converter
        if (isset($converter)) {
            $data = $converter->convert($records);
        }

        if (!isset($data)) {
            $data = $records->jsonSerialize();
        }

        // reset
        $this->reset();
        return $data;
    }

    // UPDATE

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

    // DELETE

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

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function headers(): array
    {
        $reader = $this->reader();
        return $reader->getHeader();
    }

    private function has_headers(array $headers, array $update): bool
    {
        $hasHeader = false;
        $record = $update;
        if (Records::has_multiple_records($update)) {
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

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function index(): string
    {
        $headers = $this->headers();
        return $headers[$this->config->index];
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    public function dump(string $data): void
    {
        // history
        if ($this->config->history) {
            $this->history();
        }

        // dump data to file - all data is overwritten
        file_put_contents($this->file, $data);

        // cache
        if ($this->config->cache) {
            $this->cache();
        }
    }
}