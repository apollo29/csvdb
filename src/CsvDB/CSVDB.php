<?php

namespace CSVDB;

use CSVDB\Helpers\CSVConfig;
use CSVDB\Helpers\CSVUtilities;
use League\Csv\AbstractCsv;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\TabularDataReader;
use League\Csv\Writer;

class CSVDB
{
    public string $document;
    public CSVConfig $config;

    private array $select = array();
    private array $where = array();
    private array $order = array();
    private int $limit = 0;

    const ASC = "asc";
    const DESC = "desc";

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
    private function writer(): AbstractCsv
    {
        $csv = Writer::createFromPath($this->document, "a+");
        $csv->setDelimiter($this->config->delimiter);
        return $csv;
    }

    /**
     * @throws InvalidArgument
     * @throws Exception
     */
    private function reader(): AbstractCsv
    {
        $csv = Reader::createFromPath($this->document);
        $csv->setDelimiter($this->config->delimiter);
        if ($this->config->headers) {
            $csv->setHeaderOffset(0);
        }
        return $csv;
    }

    /**
     * @throws CannotInsertRecord
     */
    public function insert(array $data)
    {
        $writer = $this->writer();
        if (is_array($data[0])) {
            $test = $writer->insertAll($data);
        } else {
            $test = $writer->insertOne($data);
        }
        var_dump($test);
    }

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

    public function where(array $where = array()): CSVDB
    {
        $this->where = $where;
        return $this;
    }

    private function where_stmt(array $row): bool
    {
        $return = true;
        if (count($this->where) > 1) {
            foreach ($this->where as $where) {
                $return = $return && $this->create_where_stmt($row, $where);
            }
        } else {
            $return = $this->create_where_stmt($row, $this->where);
        }
        return $return;
    }

    private function create_where_stmt(array $row, array $where): bool
    {
        $key = key($where);
        $value = $where[$key];
        return strpos($row[$key], $value) !== false;
    }

    public function orderBy(array $order = array()): CSVDB
    {
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
        $reader = Reader::createFromPath($this->document);
        $reader->setDelimiter($this->config->delimiter);
        if ($this->config->headers) {
            $reader->setHeaderOffset(0);
        }
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
        $data = $this->select_stmt($records);
        return $data;
    }
}