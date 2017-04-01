<?php
header('Content-Type: text/plain');
$redis = new Redis();
$connected = $redis->connect("localhost");

// ---------------------------------------------------------------------------
// Fetch hydro data
// ---------------------------------------------------------------------------
$result = json_decode(file_get_contents("http://cydynni.org.uk/hydro"));
if ($result!=null) {
    echo time()." hydro updated\n";
    $redis->set("cydynni:hydro",json_encode($result));
}

// ---------------------------------------------------------------------------
// Fetch community totals
// ---------------------------------------------------------------------------
$result = json_decode(file_get_contents("http://cydynni.org.uk/community/data"));
if ($result!=null) {
    echo time()." community data updated\n";
    $redis->set("cydynni:community:data",json_encode($result));
}

// ---------------------------------------------------------------------------
// Fetch community half hourly data
// ---------------------------------------------------------------------------
$result = json_decode(file_get_contents("http://cydynni.org.uk/community/halfhourlydata"));
if ($result!=null) {
    echo time()." community halfhourlydata updated\n";
    $redis->set("cydynni:community:halfhourlydata",json_encode($result));
}
