<html>
<head>
    <title>cntnd_contacts</title>

    <script
            src="https://code.jquery.com/jquery-3.6.3.min.js"
            integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU="
            crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/js-base64@2.5.2/base64.min.js"></script>
    <script src="https://bossanova.uk/jspreadsheet/v4/jexcel.js"></script>
    <script src="https://jsuites.net/v4/jsuites.js"></script>
    <link rel="stylesheet" href="https://jsuites.net/v4/jsuites.css" type="text/css"/>
    <link rel="stylesheet" href="https://bossanova.uk/jspreadsheet/v4/jexcel.css" type="text/css"/>

    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Material+Icons"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/cntnd/core_style@1.1.1/dist/core_style.css">

    <?php


    use CSVDB\CSVDB;
    use CSVDB\Helpers\CSVConfig;

    require '../vendor/autoload.php';

    $csvdb = new CSVDB(__DIR__ . "/csv/phpunit.csv", new CSVConfig(CSVConfig::INDEX, CSVConfig::ENCODING, CSVConfig::DELIMITER, CSVConfig::HEADERS, CSVConfig::CACHE, true, false));

    $records = [
        [1, 2, 3],
        ['foo', 'bar', 'baz'],
        ['john', 'doe', 'john.doe@example.com'],
    ];

    //$test->insert($records);


    //$test->insert(['test insert 1','test insert 2','test insert 3']);
    ?>
</head>

<body>

<h1>csvdb</h1>
<pre>
    <?php
    /*
    $data = $test
        ->select(["test1", "test test3"])
        ->where(["test1" => ["john", CSVDB::NEG]])
        ->orderBy(["test2" => CSVDB::ASC])
        ->get();

    var_dump($data);

    //$test->delete(["test1" => "john"]);

    $test->update(["test3" => "update test"], ["test1" => "john"]);
    */

    function prepareDefaultData(): array
    {
        $header = array('index', 'header1', 'header2', 'header3');
        $data = array(
            array(10, 'row1', 'test2_1', 'value5'),
            array(11, 'row2', 'test2_1', 'value4'),
            array(12, 'row3', 'test2_1', 'value3'),
            array(13, 'row4', 'test2_2', 'value2'),
            array(14, 'row5', 'test2_3', 'value1')
        );

        $raw = array();
        $raw[] = [$header[0] => $data[0][0], $header[1] => $data[0][1], $header[2] => $data[0][2], $header[3] => $data[0][3]];
        $raw[] = [$header[0] => $data[1][0], $header[1] => $data[1][1], $header[2] => $data[1][2], $header[3] => $data[1][3]];
        $raw[] = [$header[0] => $data[2][0], $header[1] => $data[2][1], $header[2] => $data[2][2], $header[3] => $data[2][3]];
        $raw[] = [$header[0] => $data[3][0], $header[1] => $data[3][1], $header[2] => $data[3][2], $header[3] => $data[3][3]];
        $raw[] = [$header[0] => $data[4][0], $header[1] => $data[4][1], $header[2] => $data[4][2], $header[3] => $data[4][3]];
        return $raw;
    }

    $test1 = prepareDefaultData();
    //$test1[0]["header2"] = "update0";
    //$test1[1]["header2"] = "update1";
    //$test1[2]["header2"] = "update2";

    //var_dump($test1[0]);
    /*
        $csvdb->insert($test1[2]);
        $data = $csvdb->select()->get();
        var_dump($data);
        $data = $csvdb->select()->orderBy(["header3" => CSVDB::ASC])->get();
        var_dump($data);
        $csvdb->upsert($test1[1]);
        $csvdb->delete(["header1"=>"row1"]);

        $data = $csvdb->select()->where(["header1" => "row3"])->get();
        var_dump($data);
    header1,header2,header3
row1,test2_1,value5
    */
    /*
        $csvdb->unique("header3", "header1");
        $csvdb->unique_index();
    */
    //$test = ["header1" => "row6", "header2" => "test2", "header3" => "value6"];
    //$result = $csvdb->insert($test);
    //var_dump($result);
    //$csvdb->upsert($test,["index"=>7]);
    //$result = $csvdb->update(["header3"=>"***UPDATE***"],["header2"=>"test2_1"]);
    //$data = $csvdb->select()->where(["header1" => "row1"])->get(); //[["header1" => "row1"],["header3" => "value1"]]
    //$data = $csvdb->select()->where([["header1" => "row1"], ["header3" => "value3"], CSVDB::OR])->get();
    //$data = $csvdb->getDatatypes();
    //$data = $csvdb->select()->get();
    //var_dump($data);
    /*
    $test2 = "test_string_1,test_string_2,test_string_3\n";
    $test2 .= "test_string1_1,test_string_1_2,test_string_1_3\n";
    $test2 .= "test_string2_1,test_string_2_2,test_string_2_3\n";
    $test2 .= "test_string3_1,test_string_3_2,test_string_3_3\n";
    $test2 .= "test_string4_1,test_string_4_2,test_string_4_3\n";
    $csvdb->dump($test2);

    $data = $csvdb->select()->get();
    */
    $csvdb->schema(array(
        'index' => array(
            "type" => "integer"
        ),
        'header1' => array(
            "type" => "string",
            "default" => "rowX"
        ),
        'header2' => array(
            "type" => "string"
        ),
        'header3' => array(
            "type" => "integer",
            "default" => "current_timestamp"
        )
    ));
    $data = $csvdb->schema->defaults();
    var_dump($data);
    //var_dump($result);
    /*

    $csvdb->schema($schema, true);
    //$result = $csvdb->insert(["index"=>10,"header1" => "row6", "header2" => "test2", "header3" => "value6","header4"=>"test"]);
    $result = $csvdb->update(["header4" => "test2"],["index"=>1]);
    */
    ?>
</pre>

</body>

</html>