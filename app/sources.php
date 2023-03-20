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

    $file = __DIR__ . "/sources.csv";
    if (!file_exists($file)) {
        $fp = fopen($file, 'w');
        fputcsv($fp, ["source", "last_load"]);
        fclose($fp);
    }
    $config = new CSVConfig(CSVConfig::INDEX, CSVConfig::ENCODING, CSVConfig::DELIMITER, CSVConfig::HEADERS, false);
    $csvdb = new CSVDB($file, $config);
    ?>
</head>

<body>

<h1>csvdb</h1>
<pre>
    <?php
    $csvdb->upsert(["source"=>"test1","last_load" => "rand()"], ["source" => "test1"]);
    $csvdb->upsert(["source"=>"test2","last_load" => "rand()"], ["source" => "test2"]);

    $data = $csvdb->select()->get();
    var_dump($data);

    echo "<hr />";

    $csvdb->update(["source"=>"test1","last_load" => rand()], ["source" => "test1"]);
    $csvdb->update(["source"=>"test2","last_load" => rand()], ["source" => "test2"]);

    $data = $csvdb->select()->get();
    var_dump($data);
    ?>
</pre>

</body>

</html>