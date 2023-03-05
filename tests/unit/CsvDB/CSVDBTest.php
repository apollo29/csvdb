<?php
declare(strict_types=1);

namespace CSVDB;

use CSVDB\Helpers\CSVConfig;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

class CSVDBTest extends TestCase
{

    /**
     * @var  vfsStreamDirectory
     */
    private $root;

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
        $this->root = vfsStream::setup("assets/");

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
        $this->assertEquals($file, $csvdb->document);
        $this->assertEquals(CSVConfig::default(), $csvdb->config);
    }

    public function testSelectDefault()
    {
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $data = $csvdb->select()->get();
        $this->assertEquals($this->prepareDefaultData(), $data);
    }

    public function testSelectDefaultLimit()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = $raw[0];
        $data1 = $csvdb->select()->limit(1)->get();
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[]=$raw[0];
        $test2[]=$raw[1];
        $test2[]=$raw[2];
        $data2 = $csvdb->select()->limit(3)->get();
        $this->assertEquals($test2, $data2);
    }

    public function testSelectDefaultOrder()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[]=$raw[4];
        $test1[]=$raw[3];
        $test1[]=$raw[2];
        $test1[]=$raw[1];
        $test1[]=$raw[0];
        $data1 = $csvdb->select()->orderBy("header3")->get();
        $this->assertEquals($test1, $data1);
    }

    public function testSelectDefaultWhere()
    {
        $raw = $this->prepareDefaultData();
        $file = vfsStream::url("assets/" . $this->filename);
        $csvdb = new CSVDB($file);

        $test1 = array();
        $test1[]=$raw[0];
        $test1[]=$raw[1];
        $test1[]=$raw[2];
        $data1 = $csvdb->select()->where(["header2"=>"test2_1"])->get();
        $this->assertEquals($test1, $data1);

        $test2 = array();
        $test2[]=$raw[3];
        $data2 = $csvdb->select()->where(["header2"=>"test2_2"])->get();
        $this->assertEquals($test2, $data2);
    }
}
