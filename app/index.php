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

    $test = new CSVDB("test.csv");

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


    $data = $test
        ->select(["test1", "test test3"])
        ->where(["test1" => ["john", CSVDB::NEG]])
        ->orderBy(["test2" => CSVDB::ASC])
        ->get();

    var_dump($data);


    //$test->delete(["test1" => "john"]);

    ?>
</pre>

</body>

</html>