<?php

namespace CSVDB\Helpers;

class CSVConfig
{
    public int $index;
    public string $encoding;
    public string $delimiter;
    public bool $headers;

    const INDEX = 0;
    const ENCODING = "UTF-8";
    const DELIMITER = ",";
    const HEADERS = true;

    /**
     * @param string $encoding
     * @param string $delimiter
     */
    public function __construct(int $index = CSVConfig::INDEX, string $encoding = CSVConfig::ENCODING, string $delimiter = CSVConfig::DELIMITER, $headers = CSVConfig::HEADERS)
    {
        $this->index = $index;
        $this->encoding = $encoding;
        $this->delimiter = $delimiter;
        $this->headers = $headers;
    }


    public static function default(): CSVConfig
    {
        return new CSVConfig();
    }
}
