<?php
declare(strict_types=1);

namespace CSVDB;

use PHPUnit\Framework\TestCase;

class CSVDBTest extends TestCase
{
    public function testClassConstructerException(): void
    {
        $this->expectExceptionMessage("Unable to open CSV file");
        $testClass = new CSVDB("test.doc");
    }
}
