<?php
declare(strict_types=1);

namespace CSVDB\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * @covers \CSVDB\Helpers\CSVConfig
 * @uses   \CSVDB\Helpers\CSVConfig
 */
class CSVConfigTest extends TestCase
{

    public function testConfigDefault()
    {
        $config = CSVConfig::default();
        $this->assertEquals($config->index, CSVConfig::INDEX);
        $this->assertEquals($config->encoding, CSVConfig::ENCODING);
        $this->assertEquals($config->delimiter, CSVConfig::DELIMITER);
        $this->assertEquals($config->headers, CSVConfig::HEADERS);
        $this->assertEquals($config->cache, CSVConfig::CACHE);
        $this->assertEquals($config->history, CSVConfig::HISTORY);
    }

    public function testConfigCustom()
    {
        $config = new CSVConfig(1, "UTF-16", ";", false, false, false);
        $this->assertEquals($config->index, 1);
        $this->assertEquals($config->encoding, "UTF-16");
        $this->assertEquals($config->delimiter, ";");
        $this->assertEquals($config->headers, false);
        $this->assertEquals($config->cache, false);
        $this->assertEquals($config->history, false);
    }
}
