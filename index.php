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
require "household_process.php";

$path = get_application_path();
$mysqli = @new mysqli($mysql['server'],$mysql['username'],$mysql['password'],$mysql['database']);

$redis = new Redis();
$connected = $redis->connect("localhost");

$local_emoncms = "http://localhost/emoncms";
$local_emoncms_apikey = "a28fa47b30c74ba9bfd5e7ee63279d47";

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
            $content = get_household_data($local_emoncms_apikey,4);
        }
        break;

    case "hydro":
        $format = "json";
        $content = json_decode($redis->get("cydynni:hydro"));
        break;
                 
    case "community/data":
        $format = "json";
        $content = json_decode($redis->get("cydynni:community:data"));
        break;
        
    case "community/halfhourlydata":
        $format = "json";
        $content = json_decode($redis->get("cydynni:community:halfhourlydata"));
        break;
        
    // ------------------------------------------------------------------------
    // Household consumption feeds from emoncms   
    // ------------------------------------------------------------------------
    
    case "data":
        $format = "json";
        // Interval
        if (isset($_GET['interval']))
            $content = json_decode(file_get_contents("$local_emoncms/feed/data.json?id=".get("id")."&start=".get("start")."&end=".get("end")."&interval=".get("interval")."&skipmissing=".get("skipmissing")."&limitinterval=".get("limitinterval")."&apikey=$local_emoncms_apikey"));
        // Mode
        if (isset($_GET['mode']))
            $content = json_decode(file_get_contents("$local_emoncms/feed/data.json?id=".get("id")."&start=".get("start")."&end=".get("end")."&mode=".get("mode")."&apikey=$local_emoncms_apikey"));
        break;
        
    case "average":
        $format = "json";
        // Interval
        if (isset($_GET['interval']))
            $content = json_decode(file_get_contents("$local_emoncms/feed/average.json?id=".get("id")."&start=".get("start")."&end=".get("end")."&interval=".get("interval")."&skipmissing=".get("skipmissing")."&limitinterval=".get("limitinterval")."&apikey=$local_emoncms_apikey"));
        // Mode
        if (isset($_GET['mode']))
            $content = json_decode(file_get_contents("$local_emoncms/feed/average.json?id=".get("id")."&start=".get("start")."&end=".get("end")."&mode=".get("mode")."&apikey=$local_emoncms_apikey"));
            
        break;
                
    case "value":
        $format = "text";
        $content = file_get_contents("$local_emoncms/feed/value.json?id=".get("id")."&apikey=$local_emoncms_apikey");
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
