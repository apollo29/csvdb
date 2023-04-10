<?php

namespace CSVDB\Schema;

use CSVDB\CSVDB;
use CSVDB\Helpers\CSVConfig;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DefaultTraitTest extends TestCase
{
    protected string $filename = "test.csv";
    protected array $header = array('header1', 'header2', 'header3');
    protected array $data = array(
        array('row1', 'test2_1', 5),
        array('row2', 'test2_1', 4),
        array('row3', 'test2_1', 3),
        array('row4', 'test2_2', 2),
        array('row5', 'test2_3', 1)
    );
    protected array $schema = array(
        'header1' => array(
            "type" => "string"
        ),
        'header2' => array(
            "type" => "string",
            "default" => "test2_1"
        ),
        'header3' => array(
            "type" => "integer",
            "default" => "current_timestamp"
        )
    );

    protected function setUp(): void
    {
        vfsStream::setup("assets/");

        $fp = fopen(vfsStream::url("assets/" . $this->filename), 'w');
        fputcsv($fp, $this->header);
        foreach ($this->data as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
    }

    private function prepareDefaultData(): array
    {
        $data = array();
        $data[] = [$this->header[0] => $this->data[0][0], $this->header[1] => $this->data[0][1], $this->header[2] => $this->data[0][2]];
        $data[] = [$this->header[0] => $this->data[1][0], $this->header[1] => $this->data[1][1], $this->header[2] => $this->data[1][2]];
        $data[] = [$this->header[0] => $this->data[2][0], $this->header[1] => $this->data[2][1], $this->header[2] => $this->data[2][2]];
        $data[] = [$this->header[0] => $this->data[3][0], $this->header[1] => $this->data[3][1], $this->header[2] => $this->data[3][2]];
        $data[] = [$this->header[0] => $this->data[4][0], $this->header[1] => $this->data[4][1], $this->header[2] => $this->data[4][2]];
        return $data;
    }

    public function testInsertDefault()
    {
        $raw = $this->prepareDefaultData();

        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);
        $csvdb->schema($this->schema);

        $record1_raw = [$this->header[0] => "row10"];
        $record2_raw = [$this->header[0] => "row11", $this->header[1] => "", $this->header[2] => CSVDB::EMPTY];

        $test1 = $raw;
        $test1[] = $record1_raw;
        $result1 = $csvdb->insert($record1_raw);
        $data1 = $csvdb->select()->get();
        $this->assertEquals(count($test1), count($data1));
        $this->assertEquals($result1[0][$this->header[0]], $record1_raw[$this->header[0]]);
        $this->assertEquals($result1[0][$this->header[1]], "test2_1");
        $this->assertTrue(is_numeric((int)$result1[0][$this->header[2]]));

        $test2 = $raw;
        $test2[] = $record1_raw;
        $test2[] = $record2_raw;
        $result2 = $csvdb->insert($record2_raw);
        $data2 = $csvdb->select()->get();
        $this->assertEquals(count($test2), count($data2));
        $this->assertEquals($result2[0][$this->header[0]], $record2_raw[$this->header[0]]);
        $this->assertEquals($result2[0][$this->header[1]], "test2_1");
        $this->assertTrue(is_numeric((int)$result2[0][$this->header[2]]));
    }

    public function testInsertNonAssoc()
    {
        $raw = $this->prepareDefaultData();

        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);
        $csvdb->schema($this->schema);

        $record1_raw = ["row11", "", CSVDB::EMPTY];

        $test1 = $raw;
        $test1[] = $record1_raw;
        $result1 = $csvdb->insert($record1_raw);
        $data1 = $csvdb->select()->get();
        $this->assertEquals(count($test1), count($data1));
        $this->assertEquals($result1[0][$this->header[0]], $record1_raw[0]);
        $this->assertEquals($result1[0][$this->header[1]], "test2_1");
        $this->assertTrue(is_numeric((int)$result1[0][$this->header[2]]));
    }

    public function testInsertNonAssocException()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);
        $csvdb->schema($this->schema);

        $record1_raw = ["row11", CSVDB::EMPTY];

        $this->expectExceptionMessage("Record is not an associative array and some Fields are missing. Please provide CSVDB::EMPTY for all Fiels with Default values");
        $csvdb->insert($record1_raw);
    }

    public function testInsertCustomFunction()
    {
        $raw = $this->prepareDefaultData();

        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file, CSVConfig::default());
        $csvdb->schema(array(
            'header1' => array(
                "type" => "string"
            ),
            'header2' => array(
                "type" => "string",
                "default" => "test2_1"
            ),
            'header3' => array(
                "type" => "string",
                "default" => "custom_function"
            )
        ), false, new CustomDefaultFunctionTest());

        $record1_raw = [$this->header[0] => "row10"];

        $test1 = $raw;
        $test1[] = $record1_raw;
        $result1 = $csvdb->insert($record1_raw);
        $data1 = $csvdb->select()->get();
        $this->assertEquals(count($test1), count($data1));
        $this->assertEquals($result1[0][$this->header[0]], $record1_raw[$this->header[0]]);
        $this->assertEquals($result1[0][$this->header[1]], "test2_1");
        $this->assertEquals($result1[0][$this->header[2]], CustomDefaultFunctionTest::VALUE);
    }
}


class CustomDefaultFunctionTest extends DefaultFunctions
{
    const VALUE = "this is a custom function";

    public function custom_function(): string
    {
        return self::VALUE;
    }
}
