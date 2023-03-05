<?php
declare(strict_types=1);

namespace CSVDB\Helpers;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class CSVUtilitiesTest extends TestCase
{
    protected string $filename = "test.csv";

    protected function setUp(): void
    {
        vfsStream::setup("assets/");
        $fp = fopen(vfsStream::url("assets/" . $this->filename), 'w');
        fputcsv($fp, array('row1', 'test2_1', 'value5'));
        fclose($fp);

        $fp = fopen(vfsStream::url("assets/test.txt"), 'w');
        fwrite($fp, "some content");
        fclose($fp);
    }

    public function testIs_csv()
    {
        $file1 = vfsStream::url("assets/" . $this->filename);
        $this->assertTrue(CSVUtilities::is_csv($file1));

        $file2 = vfsStream::url("assets/test.txt");
        $this->assertFalse(CSVUtilities::is_csv($file2));

        $file3 = vfsStream::url("assets/not_exist.csv");
        $this->assertFalse(CSVUtilities::is_csv($file3));

        $file4 = vfsStream::url("assets/not_exist.txt");
        $this->assertFalse(CSVUtilities::is_csv($file4));
    }
}
