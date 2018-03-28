<?php

$emoncms_userid = 1;
$emoncms_username = readline("emoncms_username: ");
$emoncms_password = readline("emoncms_password: ");

define('EMONCMS_EXEC', 1);
chdir("/var/www/emoncms");
require "process_settings.php";
require "Lib/EmonLogger.php";
$log = new EmonLogger(__FILE__);

require "Modules/user/user_model.php";


// Connect to MYSQL
$mysqli = @new mysqli($server,$username,$password,$database,$port);
if ( $mysqli->connect_error ) {
    echo "Can't connect to database, please verify credentials/configuration in settings.php<br />";
    if ( $display_errors ) {
        echo "Error message: <b>" . $mysqli->connect_error . "</b>";
    }
    die();
}

$user = new User($mysqli,false);

// Send request
$ch = curl_init();
curl_setopt($ch,CURLOPT_URL,"https://emoncms.org/user/auth.json");
curl_setopt($ch,CURLOPT_POST,1);
curl_setopt($ch,CURLOPT_POSTFIELDS,"username=$emoncms_username&password=$emoncms_password");
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
$result = curl_exec($ch);
curl_close($ch);

$result = json_decode($result);
if ($result!=null && isset($result->success) && $result->success) {

    // Fetch full account details from remote emoncms
    $u = json_decode(file_get_contents("https://emoncms.org/user/get.json?apikey=".$result->apikey_write));
    print json_encode($u)."\n";
    
    // Register account locally
    $result = $user->register($emoncms_username, $emoncms_password, $u->email);
    
    // Save remote account apikey to local hub
    if ($result['success']==true) {
        print "Updating apikeys\n";
        
        $userid = $result['userid'];
        $mysqli->query("UPDATE users SET apikey_write = '".$u->apikey_write."' WHERE id='$userid'");
        $mysqli->query("UPDATE users SET apikey_read = '".$u->apikey_read."' WHERE id='$userid'");
        
        $emonhubconf = file_get_contents("/home/pi/data/emonhub.conf");
        $emonhubconf = str_replace("apikey = xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx","apikey = ".$u->apikey_write,$emonhubconf);
        $fh = fopen("/home/pi/data/emonhub.conf","w");        
        fwrite($fh,$emonhubconf);                
        fclose($fh);
    } else {
        print json_encode($result)."\n";
    }
}
