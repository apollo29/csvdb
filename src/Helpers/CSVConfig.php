<?php

namespace CSVDB\Helpers;

class CSVConfig
{
    public int $index;
    public string $encoding;
    public string $delimiter;
    public bool $headers;
    public bool $cache;
    public bool $history;
    public bool $autoincrement;

    const INDEX = 0;
    const ENCODING = "UTF-8";
    const DELIMITER = ",";
    const HEADERS = true;
    const CACHE = true;
    const HISTORY = false;
    const AUTOINCREMENT = false;

    /**
     * @param string $encoding
     * @param string $delimiter
     */
    public function __construct(
        int    $index = CSVConfig::INDEX,
        string $encoding = CSVConfig::ENCODING,
        string $delimiter = CSVConfig::DELIMITER,
        bool   $headers = CSVConfig::HEADERS,
        bool   $cache = CSVConfig::CACHE,
        bool   $history = CSVConfig::HISTORY,
        bool   $autoincrement = CSVConfig::AUTOINCREMENT)
    {
        $this->index = $index;
        $this->encoding = $encoding;
        $this->delimiter = $delimiter;
        $this->headers = $headers;
        $this->cache = $cache;
        $this->history = $history;
        $this->autoincrement = $autoincrement;
    }


    public static function default(): CSVConfig
    {
        return new CSVConfig();
    }
}
