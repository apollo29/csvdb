<?php

namespace CSVDB\Query;

use CSVDB\CSVDB;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
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
            "type" => "string",
            "constraint" => "unique"
        ),
        'header2' => array(
            "type" => "string"
        ),
        'header3' => array(
            "type" => "integer"
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

    // CREATE

    public function testInsertDefault()
    {
        $raw = $this->prepareDefaultData();
        $record1 = array('record1', 'record1_1', 'value0');
        $record2 = array('record2', 'record1_1', 'value5');

        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $record1_raw = [$this->header[0] => $record1[0], $this->header[1] => $record1[1], $this->header[2] => $record1[2]];
        $test1[] = $record1_raw;
        $result1 = $csvdb->query("INSERT INTO test (header1, header2, header3) VALUES ('$record1[0]', '$record1[1]', '$record1[2]')");
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
        $this->assertEquals($result1[0], $record1_raw);

        $test2 = $raw;
        $record2_raw = [$this->header[0] => $record2[0], $this->header[1] => $record2[1], $this->header[2] => $record2[2]];
        $test2[] = $record1_raw;
        $test2[] = $record2_raw;
        $result2 = $csvdb->query("INSERT INTO test (header1, header2, header3) VALUES ('$record2[0]', '$record2[1]', '$record2[2]')");
        $data2 = $csvdb->select()->get();
        $this->assertEquals($test2, $data2);
        $this->assertEquals($result2[0], $record2_raw);
    }

    // READ

    public function testSelectDefault()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->query("SELECT * FROM test");
        $this->assertEquals($raw, $data1);
    }

    public function testSelectDefaultField()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = ['header1' => $raw[0]['header1']];
        $test1[] = ['header1' => $raw[1]['header1']];
        $test1[] = ['header1' => $raw[2]['header1']];
        $test1[] = ['header1' => $raw[3]['header1']];
        $test1[] = ['header1' => $raw[4]['header1']];
        $data1 = $csvdb->query("SELECT header1 FROM test");
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultLimit()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $data1 = $csvdb->query("SELECT * FROM test LIMIT 0, 1");
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[] = $raw[0];
        $test2[] = $raw[1];
        $test2[] = $raw[2];
        $data2 = $csvdb->query("SELECT * FROM test LIMIT 0, 3");
        $this->assertEquals($test2, $data2);


        $test3 = array();
        $test3[] = $raw[2];
        $test3[] = $raw[3];
        $data3 = $csvdb->query("SELECT * FROM test LIMIT 2, 2");
        $this->assertEquals($test3, $data3);
    }

    public function testSelectDefaultOrderASC()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[4];
        $test1[] = $raw[3];
        $test1[] = $raw[2];
        $test1[] = $raw[1];
        $test1[] = $raw[0];

        $data1 = $csvdb->query("SELECT * FROM test ORDER BY header3");
        $this->assertEquals($test1, $data1);

        $data2 = $csvdb->query("SELECT * FROM test ORDER BY header3 ASC");
        $this->assertEquals($test1, $data2);
    }

    public function testSelectDefaultOrderDESC()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->query("SELECT * FROM test ORDER BY header3 DESC");
        $this->assertEquals($raw, $data1);
    }

    public function testSelectDefaultWhere()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $test1[] = $raw[1];
        $test1[] = $raw[2];
        $data1 = $csvdb->query("SELECT * FROM test WHERE header2 = 'test2_1'");
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[] = $raw[3];
        $data2 = $csvdb->query("SELECT * FROM test WHERE header2 = 'test2_2'");
        $this->assertEquals($test2, $data2);

        $test3 = array();
        $test3[] = $raw[3];
        $test3[] = $raw[4];
        $data3 = $csvdb->query("SELECT * FROM test WHERE header2 != 'test2_1'");
        $this->assertEquals($test3, $data3);


        $data4 = $csvdb->query("SELECT * FROM test WHERE header2 <> 'test2_1'");
        $this->assertEquals($test3, $data4);
    }

    public function testSelectDefaultWhereMultipleOperator()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $data1 = $csvdb->query("SELECT * FROM test WHERE header1 = 'row1' OR header3 = 'value3' AND header2 = 'test2_1'");
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[] = $raw[0];
        $data2 = $csvdb->query("SELECT * FROM test WHERE header1 = 'row1' OR header3 = 'value3'");
        $this->assertEquals($test2, $data2);
    }

    public function testSelectDefaultWhereLike()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->query("SELECT * FROM test WHERE header2 = 'test2'");
        $this->assertEquals(array(), $data1);

        $data2 = $csvdb->query("SELECT * FROM test WHERE header2 LIKE 'test2'");
        $this->assertEquals($raw, $data2);

        $data3 = $csvdb->query("SELECT * FROM test WHERE header2 LIKE '%test2%'");
        $this->assertEquals($raw, $data3);

        $test4 = array();
        $test4[] = $raw[0];
        $test4[] = $raw[1];
        $test4[] = $raw[2];
        $data4 = $csvdb->query("SELECT * FROM test WHERE header2 LIKE '2_1%'");
        $this->assertEquals($test4, $data4);
    }

    public function testSelectDefaultWhereIn()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $test1[] = $raw[2];
        $test1[] = $raw[4];
        $data1 = $csvdb->query("SELECT * FROM test WHERE header1 IN ('row1','row3','row5')");
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultCount()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->query("SELECT COUNT(*) FROM test WHERE header2 = 'test2_1'");
        $this->assertEquals(["count" => 3], $data1);

        $data2 = $csvdb->query("SELECT COUNT(*) FROM test WHERE header2 = 'test2_1' LIMIT 0, 1");
        $this->assertEquals(["count" => 1], $data2);

        $data3 = $csvdb->query("SELECT COUNT(*) FROM test WHERE header2 = 'test2_1' AND header3 = 'value5'");
        $this->assertEquals(["count" => 0], $data3);
    }

    // UPDATE

    public function testUpdateDefaultAll()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[1]["header2"] = "update";
        $test1[2]["header2"] = "update";
        $test1[3]["header2"] = "update";
        $test1[4]["header2"] = "update";
        $csvdb->query("UPDATE test SET header2 = 'update'");
        $csvdb->update(["header2" => "update"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    public function testUpdateDefault()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[1]["header2"] = "update";
        $test1[2]["header2"] = "update";
        $result = $csvdb->query("UPDATE test SET header2 = 'update' WHERE header2 = 'test2_1'");
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
        $this->assertEquals($result, [$test1[0], $test1[1], $test1[2]]);
    }

    // DELETE

    public function testDeleteAll()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $delete = $csvdb->query("DELETE FROM test");
        $this->assertTrue($delete);
        $data1 = $csvdb->select()->get();
        $this->assertEmpty($data1);
    }

    public function testDeleteDefault()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[3];
        $test1[] = $raw[4];
        $delete = $csvdb->query("DELETE FROM test WHERE header2 = 'test2_1'");
        $this->assertTrue($delete);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    public function testDeleteMultipleWhere()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[1];
        $test1[] = $raw[2];
        $test1[] = $raw[3];
        $test1[] = $raw[4];
        $test1[] = $raw[5];
        $delete = $csvdb->query("DELETE FROM test WHERE header2 = 'test2_1' AND header3 = 'value5'");
        $this->assertTrue($delete);
        $data1 = $csvdb->select()->get();
        // todo
        //$this->assertEquals($test1, $data1);
    }
}
