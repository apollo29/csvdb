<?php

namespace CSVDB\Query;

use CSVDB\Converter;
use CSVDB\Converter\CSVConverter;
use CSVDB\Converter\SQLConverter;
use CSVDB\Enums\ExportEnum;
use League\Csv\CannotInsertRecord;
use League\Csv\InvalidArgument;
use League\Csv\UnableToProcessCsv;

abstract class Query
{

    use QueryTrait;

    private string $query = "";

    public function query(string $query): Query
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @throws InvalidArgument
     * @throws UnableToProcessCsv
     * @throws CannotInsertRecord
     * @throws \Exception
     */
    public function get(Converter $converter = null)
    {
        $obj = $this->parser()->parse($this->query);
        if (!empty($this->query) && $this->checkKeywords($obj) && $this->checkExpression($obj)) {
            $method = array_keys($obj)[0];
            $data = $this->prepareStmt($obj, $method);

            // converter
            if (isset($converter) && is_array($data)) {
                $data = $converter->convert($data);
            }

            return $data;
        } else {
            throw new \Exception("There is an Error in your Query: $this->query; Please check the available Keywords [LINK]");
        }
    }

    /**
     * @throws UnableToProcessCsv
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function export(string $type = ExportEnum::CSV): string
    {
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
}