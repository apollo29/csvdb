<?php

namespace CSVDB\Schema;

use CSVDB\CSVDB;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
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
    public static array $schema = array(
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

    public function test__construct()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $csvdb->schema(self::$schema);
        $this->assertTrue($csvdb->has_schema());
    }

    public function test__constructConstraints()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $csvdb->schema(array(
            'header1' => array(
                "type" => "string",
                "constraint" => ["unique", "not_null"]
            ),
            'header2' => array(
                "type" => "string"
            ),
            'header3' => array(
                "type" => "integer"
            )
        ));
        $this->assertTrue($csvdb->has_schema());
    }

    public function test__constructExceptionEmpty()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $this->expectExceptionMessage("Schema is empty and therefore not valid.");
        $csvdb->schema(array());
    }

    public function test__constructExceptionNonAssoc()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $this->expectExceptionMessage("Schema is a non associative Records and therefore not valid.");
        $csvdb->schema(array("string", "string", "integer"));
    }

    public function test__constructExceptionInvalidType()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $this->expectExceptionMessage('Schema is not valid. Wrong Type for header1: {"type":"text"}');
        $csvdb->schema(array(
            'header1' => array(
                "type" => "text"
            ),
            'header2' => array(
                "type" => "text"
            ),
            'header3' => array(
                "type" => "integer"
            )
        ));
    }

    public function test__constructExceptionInvalidIndex()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $this->assertFalse($csvdb->has_schema());

        $this->expectExceptionMessage('Schema is not valid. Wrong Constraint for header3: independent');
        $csvdb->schema(array(
            'header1' => array(
                "type" => "string"
            ),
            'header2' => array(
                "type" => "string"
            ),
            'header3' => array(
                "type" => "integer",
                "constraint" => "independent"
            )
        ));
    }

    public function testValidateDefault()
    {
        $raw = $this->prepareDefaultData();
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $valid1 = $csvdb->schema->validate($raw[0]);
        $this->assertTrue($valid1);

        $valid2 = $csvdb->schema->validate($raw);
        $this->assertTrue($valid2);

        $valid3 = $csvdb->schema->validate([$this->data[0][0], $this->data[0][1], $this->data[0][2]]);
        $this->assertTrue($valid3);
    }

    public function testValidateNonAssocException()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $this->expectExceptionMessage("Schema is violated: Expected Type string, but Type is integer");
        $csvdb->schema->validate([5, "test2_1", "row1"]);
    }

    public function testValidateException()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $this->expectExceptionMessage("Schema is violated: Expected Type string, but Type is integer");
        $csvdb->schema->validate([[5, "test2_1", "row1"], [4, "test2_2", "row2"], ["test", "test2_3", "row3"]]);
    }

    public function testValidateStrict()
    {
        $raw = $this->prepareDefaultData();
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema, true);

        $valid1 = $csvdb->schema->validate($raw[0]);
        $this->assertTrue($valid1);

        $valid2 = $csvdb->schema->validate($raw);
        $this->assertTrue($valid2);

        $this->expectExceptionMessage("Schema Validation is strict, non associative Records are not allowed.");
        $csvdb->schema->validate([$this->data[0][0], $this->data[0][1], $this->data[0][2]]);

        $valid4 = $csvdb->schema->validate([5, "test2_1", "row1"]);
        $this->assertFalse($valid4);

        $valid5 = $csvdb->schema->validate([[5, "test2_1", "row1"], [4, "test2_2", "row2"], ["test", "test2_3", "row3"]]);
        $this->assertFalse($valid5);
    }

    // INSERT

    public function testValidateInsertDefault()
    {
        $raw = [];
        $raw[] = [$this->header[0] => "row10", $this->header[1] => $this->data[2][1], $this->header[2] => 10];
        $raw[] = [$this->header[0] => "row11", $this->header[1] => $this->data[3][1], $this->header[2] => 11];
        $raw[] = [$this->header[0] => "row12", $this->header[1] => $this->data[4][1], $this->header[2] => 12];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $test1_record = [$this->header[0] => "row9", $this->header[1] => $this->data[2][1], $this->header[2] => 9];
        $test1 = $csvdb->insert($test1_record);
        $this->assertEquals([$test1_record], $test1);

        $test2 = $csvdb->insert($raw);
        $this->assertEquals($raw, $test2);
    }

    public function testValidateInsertNonAssoc()
    {
        $raw = $this->prepareDefaultData();
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(array(
            'header1' => array(
                "type" => "string"
            ),
            'header2' => array(
                "type" => "string"
            ),
            'header3' => array(
                "type" => "integer"
            )
        ));

        $test1_record = array_values($raw[0]);
        $test1 = $csvdb->insert($test1_record);
        $this->assertEquals([$raw[0]], $test1);
    }

    public function testValidateInsertStrict()
    {
        $raw = [];
        $raw[] = [$this->header[0] => "row10", $this->header[1] => $this->data[2][1], $this->header[2] => 10];
        $raw[] = [$this->header[0] => "row11", $this->header[1] => $this->data[3][1], $this->header[2] => 11];
        $raw[] = [$this->header[0] => "row12", $this->header[1] => $this->data[4][1], $this->header[2] => 12];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema, true);

        $test1_record = [$this->header[0] => "row9", $this->header[1] => $this->data[2][1], $this->header[2] => 9];
        $test1 = $csvdb->insert($test1_record);
        $this->assertEquals([$test1_record], $test1);

        $test2 = $csvdb->insert($raw);
        $this->assertEquals($raw, $test2);

        $test3_record = [$this->header[0] => "row20", $this->header[1] => $this->data[4][1], $this->header[1] => $this->data[2][1], "header4" => "test"];
        $this->expectExceptionMessage('Schema Validation is strict. Field(s) ["header3"] in Record is/are missing.');
        $csvdb->insert($test3_record);
    }

    public function testValidateInsertStrictMissing()
    {
        $raw = [];
        $raw[] = [$this->header[0] => "row10", $this->header[1] => $this->data[2][1], $this->header[2] => 10];
        $raw[] = [$this->header[0] => "row11", $this->header[1] => $this->data[3][1], $this->header[2] => 11];
        $raw[] = [$this->header[0] => "row12", $this->header[1] => $this->data[4][1], $this->header[2] => 12];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema, true);

        $test3_record = [$this->header[0] => "row20", $this->header[1] => $this->data[2][1]];
        $this->expectExceptionMessage('Schema Validation is strict. Field(s) ["header3"] in Record is/are missing.');
        $csvdb->insert($test3_record);
    }

    public function testValidateInsertStrictNonAssoc()
    {
        $raw = [];
        $raw[] = [$this->header[0] => "row10", $this->header[1] => $this->data[2][1], $this->header[2] => 10];
        $raw[] = [$this->header[0] => "row11", $this->header[1] => $this->data[3][1], $this->header[2] => 11];
        $raw[] = [$this->header[0] => "row12", $this->header[1] => $this->data[4][1], $this->header[2] => 12];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema, true);

        $test1_record = array_values($raw[0]);
        $this->expectExceptionMessage("Schema Validation is strict, non associative Records are not allowed.");
        $csvdb->insert($test1_record);
    }

    public function testValidateInsertNotNull()
    {
        $raw = [$this->header[0] => "row10", $this->header[1] => null, $this->header[2] => 10];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(array(
            'header1' => array(
                "type" => "string"
            ),
            'header2' => array(
                "type" => "string",
                "constraint" => "not_null"
            ),
            'header3' => array(
                "type" => "integer"
            )
        ));

        $this->expectExceptionMessage('Schema is violated: Value is empty, but has Constraint: "not_null"');
        $csvdb->insert($raw);
    }

    public function testValidateInsertNotNullEmpty()
    {
        $raw = [$this->header[0] => "row10", $this->header[1] => "", $this->header[2] => 10];

        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(array(
            'header1' => array(
                "type" => "string"
            ),
            'header2' => array(
                "type" => "string",
                "constraint" => "not_null"
            ),
            'header3' => array(
                "type" => "integer"
            )
        ));

        $this->expectExceptionMessage('Schema is violated: Value is empty, but has Constraint: "not_null"');
        $csvdb->insert($raw);
    }

    // UPDATE

    public function testValidateUpdateDefault()
    {
        $raw = $this->prepareDefaultData();
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[1]["header2"] = "update";
        $test1[2]["header2"] = "update";
        $result = $csvdb->update(["header2" => "update"], ["header2" => "test2_1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
        $this->assertEquals($result, [$test1[0], $test1[1], $test1[2]]);
    }

    public function testValidateUpdateStrict()
    {
        $raw = $this->prepareDefaultData();
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema, true);

        $test1 = $raw;
        $test1[0]["header2"] = "update";
        $test1[1]["header2"] = "update";
        $test1[2]["header2"] = "update";
        $result = $csvdb->update(["header2" => "update"], ["header2" => "test2_1"]);
        $data1 = $csvdb->select()->get();
        $this->assertEquals($test1, $data1);
        $this->assertEquals($result, [$test1[0], $test1[1], $test1[2]]);
    }

    public function testValidateUpdateException()
    {
        $csvdb = new CSVDB(vfsStream::url("assets/" . $this->filename));
        $csvdb->schema(self::$schema);

        $this->expectExceptionMessage("Schema is violated: Expected Type string, but Type is integer");
        $csvdb->update(["header2" => 5], ["header2" => "test2_1"]);
    }
}
