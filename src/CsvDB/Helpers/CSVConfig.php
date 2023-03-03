<?php

namespace CSVDB\Helpers;

class CSVConfig
{

    public string $encoding;
    public string $delimiter;
    public string $enclosure;
    public bool $headers;

    const ENCODING = "UTF-8";
    const DELIMITER = ",";
    const HEADERS = true;

    /**
     * @param string $encoding
     * @param string $delimiter
     */
    public function __construct(string $encoding = CSVConfig::ENCODING, string $delimiter = CSVConfig::DELIMITER, $headers = CSVConfig::HEADERS)
    {
        $this->encoding = $encoding;
        $this->delimiter = $delimiter;
        $this->headers = $headers;
    }


    public static function default(): CSVConfig
    {
        return new CSVConfig();
    }

    public static function withDelimiter(string $delimiter): CSVConfig
    {
        return new CSVConfig(CSVConfig::ENCODING, $delimiter);
    }
}
