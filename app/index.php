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

    require '../vendor/autoload.php';

    $csvdb = new CSVDB("phpunit.csv");

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
        $header = array('header1', 'header2', 'header3');
        $data = array(
            array('row7', 'test2_1', 'value5'),
            array('row2', 'test2_1', 'value4'),
            array('row3', 'test2_1', 'value3'),
            array('row4', 'test2_2', 'value2'),
            array('row5', 'test2_3', 'value1')
        );

        $raw = array();
        $raw[] = [$header[0] => $data[0][0], $header[1] => $data[0][1], $header[2] => $data[0][2]];
        $raw[] = [$header[0] => $data[1][0], $header[1] => $data[1][1], $header[2] => $data[1][2]];
        $raw[] = [$header[0] => $data[2][0], $header[1] => $data[2][1], $header[2] => $data[2][2]];
        $raw[] = [$header[0] => $data[3][0], $header[1] => $data[3][1], $header[2] => $data[3][2]];
        $raw[] = [$header[0] => $data[4][0], $header[1] => $data[4][1], $header[2] => $data[4][2]];
        return $raw;
    }

    $test1 = prepareDefaultData();
    $test1[0]["header2"] = "update0";
    $test1[1]["header2"] = "update1";
    $test1[2]["header2"] = "update2";

    //var_dump($test1[0]);

    $csvdb->upsert($test1[0]);
    //$csvdb->insert($test1[0]);
    //$data = $csvdb->select()->get();
    //$data = $csvdb->select()->where(["header2" => "test2_1","header3" => "value5"], CSVDB::OR)->get();
    //var_dump($data);

    ?>
</pre>

</body>

</html>