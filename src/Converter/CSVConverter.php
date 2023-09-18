<?php

namespace CSVDB\Converter;

use CSVDB\Converter;
use CSVDB\Helpers\CSVConfig;

class CSVConverter implements Converter
{
    private string $delimiter;
    private bool $headers;

    public function __construct(string $delimiter = CSVConfig::DELIMITER, bool $headers = CSVConfig::HEADERS)
    {
        $this->delimiter = $delimiter;
        $this->headers = $headers;
    }

    public function convert(iterable $records): array
    {
        $results = [];
        $first = true;
        foreach ($records as $record) {
            if ($first && $this->headers) {
                $results[] = $this->to_csv(array_keys($record));
                $first = false;
            }
            $results[] = $this->to_csv(array_values($record));
        }
        return $results;
    }

    private function to_csv(array $record): string
    {
        return implode($this->delimiter, $record);
    }
}