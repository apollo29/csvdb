<?php

namespace CSVDB;

use CSVDB\Builder\CreateTrait;
use CSVDB\Builder\DeleteTrait;
use CSVDB\Builder\ReadTrait;
use CSVDB\Builder\UpdateTrait;
use CSVDB\Cache\CacheTrait;
use CSVDB\Converter\CSVConverter;
use CSVDB\Converter\SQLConverter;
use CSVDB\Enums\ExportEnum;
use CSVDB\Helpers\CSVConfig;
use CSVDB\Helpers\CSVUtilities;
use CSVDB\Helpers\DatatypeTrait;
use CSVDB\Helpers\Records;
use CSVDB\History\HistoryTrait;
use CSVDB\Query\Query;
use CSVDB\Query\QueryBuilder;
use CSVDB\Schema\ConstraintsTrait;
use CSVDB\Schema\DefaultTrait;
use CSVDB\Schema\Schema;
use CSVDB\Schema\SchemaTrait;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\TabularDataReader;
use League\Csv\UnableToProcessCsv;
use League\Csv\Writer;

class CSVDB implements Builder\Statement
{
    use CacheTrait;
    use HistoryTrait;

    use CreateTrait;
    use ReadTrait;
    use UpdateTrait;
    use DeleteTrait;

    use SchemaTrait;
    use ConstraintsTrait;
    use DefaultTrait;
    use DatatypeTrait;

    public string $file;
    public string $database;
    public string $document;
    public string $basedir;
    public CSVConfig $config;
    public ?Schema $schema;
    public string $index;

    private array $select = array();
    private array $where = array();
    private string $operator = CSVDB::AND;
    private array $order = array();
    private int $limit = 0;
    private int $offset = 0;
    private bool $count = false;

    private Reader $cache;

    private array $constraints = array();

    const ASC = "asc";
    const DESC = "desc";

    const AND = "and";
    const OR = "or";

    const NEG = "[CSVDB::NEG]";
    const LIKE = "[CSVDB::LIKE]";
    const GT = "[CSVDB::GT]";
    const GTE = "[CSVDB::GTE]";
    const LT = "[CSVDB::LT]";
    const LTE = "[CSVDB::LTE]";
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
        $this->database = CSVUtilities::csv_database($file);
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
    private function setup_index(): string
    {
        return $this->headers()[$this->config->index];
    }

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

    public function limit(int $limit = 0, int $offset = 0): Builder\Statement
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function prepare(): TabularDataReader
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

        // offset
        if ($this->offset > 0) {
            $stmt = $stmt->offset($this->offset);
        }
        
        $records = $stmt->process($reader);

        return $records;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     * @throws UnableToProcessCsv
     */
    public function get(Converter $converter = null): array
    {
        $records = $this->prepare();

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
       

        // select
        if (count($this->select) > 0) {
            $data = $this->filter_select($data);
        }

        // reset
        $this->reset();

        return $data;
    }

    private function filter_select(array $data): array {
        $filter=[];
        foreach($data as $row) {
            $filter[]=array_filter($row, function($k) {
                return in_array($k, $this->select);
            }, ARRAY_FILTER_USE_KEY);
        }
        return $filter;
    }

    /**
     * @throws InvalidArgument
     * @throws \ReflectionException
     * @throws Exception
     * @throws \Exception|UnableToProcessCsv
     */
    public function export(string $type = ExportEnum::CSV): string
    {
        if ($this->count) {
            throw new \Exception("Invalid Export: Count is not possible as Export Statement");
        }
        if (!ExportEnum::isValid($type)) {
            throw new \Exception("Invalid Export Type: $type");
        } else {
            switch ($type) {
                case ExportEnum::JSON:
                    return json_encode($this->get(), JSON_PRETTY_PRINT);
                case ExportEnum::SQL:
                    return implode("\n", $this->get(new SQLConverter($this->database)));
                case ExportEnum::PHP:
                    return var_export($this->get(), true);
                default:
                    return implode("\n", $this->get(new CSVConverter($this->config->delimiter, $this->config->headers)));
            }
        }
    }

    // Query

    public function query(string $query): Query
    {
        return new QueryBuilder($query, $this);
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
        $this->query = null;
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