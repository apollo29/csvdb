<?php
declare(strict_types=1);

namespace CSVDB;

use CSVDB\Helpers\CSVConfig;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CSVDB\CSVDB
 * @uses   \CSVDB\CSVDB
 */
class CSVDBTest extends TestCase
{

    protected string $filename = "test.csv";
    protected array $header = array('header1', 'header2', 'header3');
    protected array $data = array(
        array('row1', 'test2_1', 'value5'),
        array('row2', 'test2_1', 'value4'),
        array('row3', 'test2_1', 'value3'),
        array('row4', 'test2_2', 'value2'),
        array('row5', 'test2_3', 'value1')
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

    private function prepareCustomData(): array
    {
        $data = array();
        $data[] = [$this->header[0] => $this->data[0][0], $this->header[1] => $this->data[0][1]];
        $data[] = [$this->header[0] => $this->data[1][0], $this->header[1] => $this->data[1][1]];
        $data[] = [$this->header[0] => $this->data[2][0], $this->header[1] => $this->data[2][1]];
        $data[] = [$this->header[0] => $this->data[3][0], $this->header[1] => $this->data[3][1]];
        $data[] = [$this->header[0] => $this->data[4][0], $this->header[1] => $this->data[4][1]];
        return $data;
    }

    public function testClassConstructorException(): void
    {
        $this->expectExceptionMessage("Unable to open CSV file");
        new CSVDB("test.doc");
    }

    public function testClassConstructorNoFile(): void
    {
        $this->expectExceptionMessage("Unable to open CSV file");
        new CSVDB("test.csv");
    }

    public function testClassConstructorDefault(): void
    {
        $file = vfsStream::url("assets/" . $this->filename);

        $csvdb = new CSVDB($file);
        $this->assertEquals($file, $csvdb->file);
        //todo document, basedir etc...
        $this->assertEquals(CSVConfig::default(), $csvdb->config);
    }

    // CREATE

    public function testInsertDefault()
    {
        $raw = $this->prepareDefaultData();
        $record1 = array('record1', 'record1_1', 'value0');
        $record2 = array('record2', 'record1_1', 'value5');
        $record3 = array('record3', 'record1_1', 'value6');

        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[] = [$this->header[0] => $record1[0], $this->header[1] => $record1[1], $this->header[2] => $record1[2]];
        $csvdb->insert($record1);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);

        $test2 = $raw;
        $test2[] = [$this->header[0] => $record1[0], $this->header[1] => $record1[1], $this->header[2] => $record1[2]];
        $test2[] = [$this->header[0] => $record2[0], $this->header[1] => $record2[1], $this->header[2] => $record2[2]];
        $test2[] = [$this->header[0] => $record3[0], $this->header[1] => $record3[1], $this->header[2] => $record3[2]];
        $csvdb->insert(array($record2, $record3));
        $data2 = $csvdb->select()->get();
        $this->assertEquals($test2, $data2);
    }

    // READ

    public function testSelectDefault()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->select()->get();
        $this->assertEquals($raw, $data1);
    }

    public function testSelectDefaultLimit()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $data1 = $csvdb->select()->limit(1)->get();
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[] = $raw[0];
        $test2[] = $raw[1];
        $test2[] = $raw[2];
        $data2 = $csvdb->select()->limit(3)->get();
        $this->assertEquals($test2, $data2);
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

        $data1 = $csvdb->select()->orderBy("header3")->get();
        $this->assertEquals($test1, $data1);

        $data2 = $csvdb->select()->orderBy(["header3"])->get();
        $this->assertEquals($test1, $data2);

        $data3 = $csvdb->select()->orderBy(["header3" => CSVDB::ASC])->get();
        $this->assertEquals($test1, $data3);
    }

    public function testSelectDefaultOrderDESC()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->select()->orderBy(["header3" => CSVDB::DESC])->get();
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
        $data1 = $csvdb->select()->where(["header2" => "test2_1"])->get();
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[] = $raw[3];
        $data2 = $csvdb->select()->where(["header2" => "test2_2"])->get();
        $this->assertEquals($test2, $data2);

        $test3 = array();
        $test3[] = $raw[3];
        $test3[] = $raw[4];
        $data3 = $csvdb->select()->where(["header2" => ["test2_1", CSVDB::NEG]])->get();
        $this->assertEquals($test3, $data3);
    }

    public function testSelectCustomWhere()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $data1 = $csvdb->select()->where([["header2" => "test2_1"], ["header3" => "value5"]])->get();
        $this->assertEquals($test1, $data1);
    }

    public function testSelectCustomWhereOR()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[0];
        $test1[] = $raw[1];
        $test1[] = $raw[2];
        $data1 = $csvdb->select()->where([["header2" => "test2_1"], ["header3" => "value5"]], CSVDB::OR)->get();
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultWhereOrder()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[2];
        $test1[] = $raw[1];
        $test1[] = $raw[0];
        $data1 = $csvdb->select()->where(["header2" => "test2_1"])->orderBy("header3")->get();
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultWhereOrderLimit()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[] = $raw[2];
        $test1[] = $raw[1];
        $data1 = $csvdb->select()->where(["header2" => "test2_1"])->orderBy("header3")->limit(2)->get();
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultCount()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data1 = $csvdb->select()->count()->where(["header2" => "test2_1"])->get();
        $this->assertEquals(["count" => 3], $data1);

        $data2 = $csvdb->select()->count()->where(["header2" => "test2_1"])->limit(1)->get();
        $this->assertEquals(["count" => 1], $data2);

        $data3 = $csvdb->select()->count()->where([["header2" => "test2_1"], ["header3" => "value5"]])->get();
        $this->assertEquals(["count" => 1], $data3);
    }

    public function testSelectCustom()
    {
        $raw = $this->prepareCustomData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $data1 = $csvdb->select(["header1", "header2"])->get();
        $this->assertEquals($test1, $data1);
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
        $csvdb->update(["header2" => "update"], ["header2" => "test2_1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    public function testUpdateDefaultMultipleFields()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[0]["header3"] = "update2";
        $test1[1]["header2"] = "update";
        $test1[1]["header3"] = "update2";
        $test1[2]["header2"] = "update";
        $test1[2]["header3"] = "update2";
        $csvdb->update(["header2" => "update", "header3" => "update2"], ["header2" => "test2_1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    public function testUpdateException(): void
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $this->expectExceptionMessage("Nothing to update.");
        $csvdb->update(array());
    }

    public function testUpdateExceptionArray()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $this->expectExceptionMessage("Update is not an associative array.");
        $csvdb->update(["test_exception", "test", "test"]);
    }

    public function testUpsertExceptionMultipleRows()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[1]["header2"] = "update";
        $test1[2]["header2"] = "update";

        $this->expectExceptionMessage("Update/insert only one row.");
        $csvdb->upsert($test1);
    }

    public function testUpsertExceptionNoRows(): void
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $this->expectExceptionMessage("Nothing to update/insert.");
        $csvdb->upsert(array());
    }

    public function testUpsertUpdate()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw;
        $test1[0]["header2"] = "update";

        $csvdb->upsert($test1[0], ["header1" => "row1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    public function testUpsertInsert()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $record1 = array('header1' => 'record1', 'header2' => 'record1_1', 'header3' => 'value0');
        $test1 = $raw;
        $test1[5] = $record1;
        $csvdb->upsert($record1);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    // DELETE

    public function testDeleteAll()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $csvdb->delete();
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
        $csvdb->delete(["header2" => "test2_1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
    }

    // CONVERTER

    //todo
}
