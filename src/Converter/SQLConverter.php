<?php

namespace CSVDB\Converter;

use CSVDB\Converter;

class SQLConverter implements Converter
{
    private string $database;

    public function __construct(string $database)
    {
        $this->database = $database;
    }

    public function convert(iterable $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $headers = array_keys($record);
            $values = array_values($record);
            $results[] = "INSERT INTO ".$this->database." (".implode(",",$headers).") VALUES ('".implode("','",$values)."');";
        }
        return $results;
    }
}