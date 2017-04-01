<?php

/*

Source code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
CydYnni App - community energy smart grid web app
part of the EnergyLocal CydYnni project in Bethesda North Wales

Developed by OpenEnergyMonitor:
http://openenergymonitor.org

*/

error_reporting(E_ALL);
ini_set('display_errors', 'on');
date_default_timezone_set('Europe/London');

// ---------------------------------------------------------
$test_user = 59;
// ---------------------------------------------------------
require "settings.php";
require "core.php";
require "meter_data_api.php";
require "mysql_store.php";
require "test_user.php";

$path = get_application_path();
$mysqli = @new mysqli($mysql['server'],$mysql['username'],$mysql['password'],$mysql['database']);

$redis = new Redis();
$connected = $redis->connect("localhost");

// ---------------------------------------------------------
require("user_model.php");
$user = new User($mysqli);

ini_set('session.cookie_lifetime', 60 * 60 * 24 * 7);
session_start();
$session = $user->status();

if ($session) {
    $userid = (int) $session['userid'];
    $mysqli->query("UPDATE users SET hits=hits+1 WHERE `id`='$userid'");
}

// ---------------------------------------------------------

$q = "";
if (isset($_GET['q'])) $q = $_GET['q'];

$translation = new stdClass();
$translation->cy = json_decode(file_get_contents("locale/cy"));

$lang = "cy";
if (isset($_GET['lang']) && $_GET['lang']=="cy") $lang = "cy";
if (isset($_GET['iaith']) && $_GET['iaith']=="cy") $lang = "cy";
if (isset($_GET['lang']) && $_GET['lang']=="en") $lang = "en";
if (isset($_GET['iaith']) && $_GET['iaith']=="en") $lang = "en";


$format = "html";
$content = "Sorry page not found";

$logger = new EmonLogger();
switch ($q)
{   

    case "":
        $format = "html";
        if ($session) $rsession = array('email'=>$session['email']); else $rsession = false;
        $content = view("pages/client.php",array('session'=>$rsession));
        break;
                        
    case "household/data":
        if ($session && isset($session['apikey'])) {
            $format = "json";
            $content = get_household_consumption($meter_data_api_baseurl,$session['apikey']);
        }
        if (isset($session["userid"]) && $session["userid"]==$test_user) $content = $test_user_household_last_day_summary;
        break;

    case "hydro":
        $format = "json";
        $content = json_decode($redis->get("cydynni:hydro"));
        break;
                 
    case "community/data":
        $format = "json";
        $content = json_decode($redis->get("cydynni:community:data"));
        //$content = get_community_consumption($meter_data_api_baseurl,$meter_data_api_hydrotoken);
        break;
        
    case "community/halfhourlydata":
        $format = "json";
        $content = json_decode($redis->get("cydynni:community:halfhourlydata"));
        //$content = get_meter_data($meter_data_api_baseurl,$meter_data_api_hydrotoken,11);
        break;
        
    // ------------------------------------------------------------------------
    // Emoncms.org feed    
    // ------------------------------------------------------------------------
    
    case "data":
        $format = "json";
        if ($session && isset($session['apikey'])) $content = get_meter_data($meter_data_api_baseurl,$session['apikey'],10);
        // test user:
        if (isset($session["userid"]) && $session["userid"]==$test_user) $content = $test_user_household_meter_data;
        break;
    
    // ------------------------------------------------------------------------
    // User    
    // ------------------------------------------------------------------------
    case "status":
        $format = "json";
        $content = $session;
        break;
                
    case "login":
        $format = "json";
        $content = $user->login(get('email'),get('password'));
        break;
        
    case "logout":
        $format = "text";
        $content = $user->logout();
        break;
        
    case "passwordreset":
        $format = "text";
        $content = $user->passwordreset(get('email'));
        break;
        
    case "changepassword":
        $format = "text";
        if ($session && isset($session['userid']) && $session['userid']>0) {
            $content = $user->change_password($session['userid'], post("old"), post("new"));
        } else {
            $content = "session not valid";
        }
        break;
}

switch ($format) 
{
    case "html":
        header('Content-Type: text/html');
        print $content;
        break;
    case "text":
        header('Content-Type: text/plain');
        print $content;
        break;
    case "json":
        header('Content-Type: application/json');
        print json_encode($content);
        break;
}

class EmonLogger {
    public function __construct() {}
    public function info ($message){ }
    public function warn ($message){ }
}
