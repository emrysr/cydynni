<?php
/*

All Emoncms code is released under the GNU Affero General Public License.
See COPYRIGHT.txt and LICENSE.txt.

---------------------------------------------------------------------
Emoncms - open source energy visualisation
Part of the OpenEnergyMonitor project:
http://openenergymonitor.org

*/



// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function cydynni_controller()
{
    global $mysqli, $redis, $session, $route, $user, $settings;
    global $tariffs, $club_settings;
    global $lang;

    if (isset($_GET['lang']) && $_GET['lang']=="cy") $session['lang'] = "cy_GB";
    if (isset($_GET['iaith']) && $_GET['iaith']=="cy") $session['lang'] = "cy_GB";
    if (isset($_GET['lang']) && $_GET['lang']=="en") $session['lang'] = "en_GB";
    if (isset($_GET['iaith']) && $_GET['iaith']=="en") $session['lang'] = "en_GB";
    $lang = $session["lang"];
    
    $result = false;
    
    $route->format = "json";
    $result = false;
    require "Modules/cydynni/cydynni_model.php";
    require "Modules/cydynni/meter_data_api.php";
    $cydynni = new Cydynni($mysqli,$redis);

    $club = "bethesda";
    
    if ($settings["cydynni"]["is_hub"]) {
	      $club_settings = array();
	      $club_settings[$club] = array(
	          "name"=>"Bethesda",
	          "generator"=>"hydro",
	          "languages"=>array("cy","en"),
	          "generation_feed"=>1,
	          "consumption_feed"=>2
	      );
	      
        $tariffs = array(
          "bethesda"=>array(
              "generation"=>array("name"=>"Hydro","cost"=>0.115,"color"=>"#29aae3"),
              "morning"=>array("name"=>"Morning","cost"=>0.182,"color"=>"#ffdc00"),
              "midday"=>array("name"=>"Midday","cost"=>0.166,"color"=>"#4abd3e"),
              "evening"=>array("name"=>"Evening","cost"=>0.202,"color"=>"#c92760"),
              "overnight"=>array("name"=>"Overnight","cost"=>0.1305,"color"=>"#274e3f")
          )
        );
	  }
	  
	  global $translation;
	  $translation = new stdClass();
    $translation->cy_GB = json_decode(file_get_contents("Modules/cydynni/app/locale/cy_GB"));

    $base_url = $settings["cydynni"]["is_hub"] ? "https://dashboard.energylocal.org.uk/cydynni/" : "http://localhost/cydynni/";
    $emoncms_url = $settings["cydynni"]["is_hub"] ? 'http://localhost/emoncms/' : 'https://dashboard.energylocal.org.uk/';

    if ($session["read"]) {
        $userid = (int) $session["userid"];
                
        $result = $mysqli->query("SELECT email,apikey_read FROM users WHERE `id`='$userid'");
        $row = $result->fetch_object();
        $session["email"] = $row->email;
        $session["apikey_read"] = $row->apikey_read;
    }
    
    switch ($route->action)
    {
        case "":
            if ($session["read"]) {
                $userid = (int) $session["userid"];
                
                require_once "Modules/feed/feed_model.php";
                $feed = new Feed($mysqli,$redis,$settings["feed"]);
                
                $tmp = $feed->get_user_feeds($userid);
                
                $session["feeds"] = array();
                foreach ($tmp as $f) {
                    $session["feeds"][$f["name"]] = (int) $f["id"];
                }
                if (!$session["admin"]) $redis->incr("userhits:$userid");
            }

            $route->format = "html";

            $content = view("Modules/cydynni/app/client_view.php",array('is_hub'=>$settings["cydynni"]["is_hub"], 'session'=>$session,'club'=>$club,'club_settings'=>$club_settings[$club]));
            return array('content'=>$content,'page_classes'=>array('collapsed','manual'));
            break;

        case "report":
            if ($session["read"]) {
                $userid = (int) $session["userid"];
                if (!$session["admin"]) $redis->incr("userhits:$userid");
                $route->format = "html";
                return view("Modules/cydynni/app/report_view.php",array('session'=>$session,'club'=>$club,'club_settings'=>$club_settings[$club]));
            }
            break;
            
        // -----------------------------------------------------------------------------------------
        // Live
        // -----------------------------------------------------------------------------------------
        case "live":
            $route->format = "json";
            
            $live = new stdClass();

            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($mysqli,$redis,$settings["feed"]);
            $live->generation = number_format($feed->get_value(1),3)*2.0;
            $live->club = number_format($feed->get_value(2),3)*2.0;
            
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone("Europe/London"));
            $date->setTimestamp(time());
            $hour = $date->format("H");

            $tariff = "";
            if ($hour<7) $tariff = "overnight";
            if ($hour>=7 && $hour<16) $tariff = "daytime";
            if ($hour>=16 && $hour<20) $tariff = "evening";
            if ($hour>=20) $tariff = "overnight";
            if ($live->generation>=$live->club) $tariff = "generation";
                
            $live->tariff = $tariff;

            $imprt = 0.0;
            if ($live->generation<=$live->club) $imprt = $live->club - $live->generation;
            $selfuse = $live->club - $imprt;
            
            $hydro_price = 0.0;
            $import_price = 0.0;
            // hydro price
            if ($hour>=20.0 || $hour<7.0) $hydro_price = 5.8;
            if ($hour>=7.0 && $hour<16.0) $hydro_price = 10.4;
            if ($hour>=16.0 && $hour<20.0) $hydro_price = 12.7;
            $hydro_cost = $selfuse * $hydro_price;
            // import price
            if ($hour>=20.0 || $hour<7.0) $import_price = 10.5;
            if ($hour>=7.0 && $hour<16.0) $import_price = 18.9;
            if ($hour>=16.0 && $hour<20.0) $import_price = 23.1;
            $import_cost = $imprt * $import_price;
            // unit price
            $live->unit_price = ($import_cost + $hydro_cost) / $live->club;

            return $live;
            break;
        
        case "household-daily-summary":
            $route->format = "json";
            if ($session["read"]) {
                $userid = $session["userid"];
                
                $data = json_decode($redis->get("household:daily:summary:$userid"));
                
                if (isset($_GET['start']) && isset($_GET['end'])) {
                    $start = $_GET['start']*0.001;
                    $end = $_GET['end']*0.001;
                    $tmp = array();
                    if ($data) {
                        for ($i=0; $i<count($data); $i++) {
                            if ($data[$i][0]>=$start && $data[$i][0]<=$end) {
                                $tmp[] = $item;
                            }
                        }
                    }
                    $data = $tmp;
                }
                
                return $data;
            } else {
                return "session not valid";
            }
            break;

        case "household-summary-monthly":
            $format = "json";
            if ($session["read"]) {
                $userid = (int) $session["userid"];
                if ($result = $redis->get("household:summary:monthly:$userid")) {
                    return json_decode($result);
                }
            } else {
                return "session not valid";
            }

/*
        case "household-summary-monthly":
            $format = "json";
            if ($session["read"]) {
                $userid = (int) $session["userid"];
                return $cydynni->getHouseholdSummaryMonthly($userid,get("month"),$session["apikey_read"]);
            } else {
                return "session not valid";
            }
            break;
*/

            break;
            
        case "club-summary-day":
            $route->format = "json";

            if (!$result = $redis->get("$club:club:summary:day")) {
                if($settings["cydynni"]["is_hub"]) {
                    $result = file_get_contents("$base_url/club/summary/day");
                    if ($result) $redis->set("community:summary:day",$result);
                }
            }
            $content = json_decode($result);
            
            $date = new DateTime();
            $date->setTimezone(new DateTimeZone("Europe/London"));
            $date->setTimestamp(time());
            $date->modify("midnight");
            $time = $date->getTimestamp();
            if ($content){
                $content->dayoffset = ($time - decode_date($content->date))/(3600*24);
            } else {
                return "Invalid data";
            }
            
            $content->time = decode_date($content->date);
            return $content;
            break;

        case "club-summary-monthly":
            $format = "json";
            $month = get("month");

            if ($settings["cydynni"]["is_hub"]) {
                return json_decode(file_get_contents("$base_url/club-summary-monthly?month=$month"));
            }else{
                if ($result = $redis->get("$club:club:summary:monthly")) {
                    return json_decode($result);
                }
                return $result;
            }
            break;
                    
        case "generation-estimate":
            $route->format = "json";

            $interval = (int) $_GET['interval'];
            if (isset($_GET['lasttime'])) $estimatestart = $_GET['lasttime'];
            if (isset($_GET['lastvalue'])) $lastvalue = $_GET['lastvalue'];
                    
            if (isset($_GET['start']) && isset($_GET['end'])) {
                $end = $_GET['end'];
                $start = $_GET['start'];
            
            } else {
                $end = time() * 1000;
                $start = $estimatestart;
            }
            
            $feedid = 166913;
            //$feedid = 384377;
            if ($club=="towerpower") $feedid = 179247;
            
            $url = "https://emoncms.org/feed/average.json?";
            $url .= http_build_query(array("id"=>$feedid,"start"=>$estimatestart,"end"=>$end,"interval"=>$interval,"skipmissing"=>0,"limitinterval"=>1));
            $result = @file_get_contents($url);

            if ($result) {
                $data = json_decode($result);
                if ($data!=null && is_array($data)) {
            
                    $scale = 1.1;  
                    // Scale ynni padarn peris data and impose min/max limits
                    for ($i=0; $i<count($data); $i++) {
                        if ($data[$i][1]==null) $data[$i][1] = 0;
                        if ($club=="bethesda") {
                        
                            $data[$i][1] = ((($data[$i][1] * 0.001)-4.5) * $scale);
                            //$data[$i][1] = $data[$i][1] * 0.001;
                            if ($data[$i][1]<0) $data[$i][1] = 0;
                            if ($data[$i][1]>49) $data[$i][1] = 49;
                        } else if ($club=="towerpower") {
                            $data[$i][1] = -1 * $data[$i][1] * 0.001;
                        }
                    }
            
                    // remove last half hour if null
                    if ($data[count($data)-1][1]==null) unset($data[count($data)-1]);
            
                    return $data;
                } else {
                    return $result;
                }
            } else {
                return array();
            }  
            
            break;
            
        case "club-estimate":
            $route->format = "json";
            
            $end = (int) $_GET['lasttime'];
            $interval = (int) $_GET['interval'];
            
            $start = $end - (3600*24.0*7*1000);
            
            $data = json_decode(file_get_contents($emoncms_url."feed/average.json?id=".$club_settings[$club]["consumption_feed"]."&start=$start&end=$end&interval=$interval"));
        
            $divisions = round((24*3600) / $interval);

            $days = count($data)/$divisions;
            // Quick quality check
            if ($days==round($days)) {
            
                $consumption_profile_tmp = array();
                for ($h=0; $h<$divisions; $h++) $consumption_profile_tmp[$h] = 0;
                
                $i = 0;
                for ($d=0; $d<$days; $d++) {
                    for ($h=0; $h<$divisions; $h++) {
                        $consumption_profile_tmp[$h] += $data[$i][1]*1;
                        $i++;
                    }
                }
                
                for ($h=0; $h<$divisions; $h++) {
                    $consumption_profile_tmp[$h] = $consumption_profile_tmp[$h] / $days;
                    $consumption_profile[] = number_format($consumption_profile_tmp[$h],2);
                }
                return $consumption_profile;
            } else {
                return "session not valid";
            }
            
            break;

        case "demandshaper":
            $format = "json";
            if ($result = $redis->get("$club:club:demandshaper")) {
                return json_decode($result);
            }
            break;

        case "login":
            if (!$session['read']) {
            
                if ($user->get_number_of_users()>0) {
                    return $user->login(post('username'),post('password'),post('rememberme'));
                    
                } else if ($settings["cydynni"]["is_hub"]) {
                    $username = $_POST['username'];
                    $password = $_POST['password'];
                    
                    // Send request
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL,"https://dashboard.energylocal.org.uk/user/auth.json");
                    curl_setopt($ch,CURLOPT_POST,1);
                    curl_setopt($ch,CURLOPT_POSTFIELDS,"username=$username&password=".$password);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
                    $result = curl_exec($ch);
                    curl_close($ch);

                    $result = json_decode($result);
                    if ($result!=null && isset($result->success) && $result->success) {

                        // Fetch full account details from remote emoncms
                        $u = json_decode(file_get_contents("https://dashboard.energylocal.org.uk/user/get.json?apikey=".$result->apikey_write));

                        // Register account locally
                        $result = $user->register($username, $password, $u->email);

                        // Save remote account apikey to local hub
                        if ($result['success']==true) {
                            $userid = $result['userid'];
                            $mysqli->query("UPDATE users SET apikey_write = '".$u->apikey_write."' WHERE id='$userid'");
                            $mysqli->query("UPDATE users SET apikey_read = '".$u->apikey_read."' WHERE id='$userid'");

                            // Trigger download of user data
                            $sync_script = $settings['emoncms_dir']."/modules/cydynni/scripts-hub/cydynni-sync.sh";
                            $sync_logfile = "/var/log/emoncms/cydynni-sync.log";
                            $redis->rpush("service-runner","$sync_script>$sync_logfile");

		            // Setup remote access
                            $host = "dashboard.energylocal.org.uk";
                            $config_file = $settings['emoncms_dir']."/modules/remoteaccess-client/remoteaccess.json";
                            $config = json_decode(file_get_contents($config_file));
                            if ($config!=null) {
                                $config->APIKEY_WRITE = $u->apikey_write;
                                $config->APIKEY_READ = $u->apikey_read;
                                $config->MQTT_HOST = $host;
                                $config->MQTT_USERNAME = $username;
                                $config->MQTT_PASSWORD = $u->apikey_write;
                                $fh = fopen($settings['emoncms_dir']."/modules/remoteaccess-client/remoteaccess.json","w");
                                fwrite($fh,json_encode($config, JSON_PRETTY_PRINT));
                                fclose($fh);
                            }
		            sleep(3);
                            $content = $user->login($username, $password, false);

                            return array("success"=>true);

                        } else {
                            return array("success"=>false, "message"=>"error creating account");
                        }
                    } else {
                        return array("success"=>false, "message"=>"cydynni online account not found");
                    }
                }
            }
            break;

        case "passwordreset":
            if (!$settings["cydynni"]["is_hub"]) {    
                $format = "json";
                $user->appname = "Cydynni";
                $users = $user->get_usernames_by_email(get('email'));
                if ($users && count($users)) return $user->passwordreset($users[0]["username"],get('email'));
                else return array("success"=>false, "message"=>"User not found");
            }   
        	  break;
    	    
        // ----------------------------------------------------------------------
        // Administration functions 
        // ----------------------------------------------------------------------
        case "admin":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "html";
                unset($session["token"]);
                return view("Modules/cydynni/app/admin_view.php",array('session'=>$session));
            }
            break;
            
        case "admin-users":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "json";
                if ($session['admin']) {
                    // Include data from cydynni table here too
                    $result = $mysqli->query("SELECT id,username,email,apikey_read,admin FROM users ORDER BY id ASC");
                    $users = array();
                    while($row = $result->fetch_object()) {
                        $userid = $row->id;
                        // Include fields from cydynni table
                        $user_result = $mysqli->query("SELECT mpan,token,welcomedate,reportdate FROM cydynni WHERE `userid`='$userid'");
                        $user_row = $user_result->fetch_object();
                        if ($user_row) {
                            foreach ($user_row as $key=>$val) $row->$key = $user_row->$key;
                        }
                        $row->hits = $redis->get("userhits:$userid");
                        $row->testdata = json_decode($redis->get("user:summary:lastday:$userid"));
                        
                        $result1 = $mysqli->query("SELECT * FROM feeds WHERE `userid`='$userid'");
                        $row->feeds = $result1->num_rows;
                        
                        $users[] = $row;
                    }
                    return $users;
                }
            }
            break;
            
        case "admin-users-csv":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "text";
                if ($session['admin']) {
                    // Include data from cydynni table here too
                    $result = $mysqli->query("SELECT id,username,email,admin FROM users ORDER BY id ASC");
                    $users = array();
                    while($row = $result->fetch_object()) {
                        $userid = $row->id;
                        // Include fields from cydynni table
                        $user_result = $mysqli->query("SELECT mpan,welcomedate,reportdate FROM cydynni WHERE `userid`='$userid'");
                        $user_row = $user_result->fetch_object();
                        if ($user_row) {
                            foreach ($user_row as $key=>$val) $row->$key = $user_row->$key;
                        }
                        $row->hits = $redis->get("userhits:$userid");
                        $users[] = $row;
                    }
                    
                    $content = "";
                    foreach ($users as $user) {
                        $tmp = array();
                        foreach ($user as $key=>$val) {
                            $tmp[] = $val;
                        }
                        $content .= implode(",",$tmp)."\n";
                    }
                    return $content;
                }
            }
            break;
            
        case "admin-registeremail":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "text";
                if ($session['admin']) {
                    require("Lib/email.php");
                    require("Modules/cydynni/cydynni_emails.php");
                    $cydynni_emails = new CydynniEmails($mysqli);
                    return $cydynni_emails->registeremail(get('userid'));
                }
            }
            break;
            
        case "admin-change-user-email":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "json";
                if ($session['admin']) {
                    return $user->change_email(get("userid"),get("email"));
                }
            }
            break;

        case "admin-change-user-username":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "json";
                if ($session['admin']) {
                    return $user->change_username(get("userid"),get("username"));
                }
            }
            break;
                    
        case "admin-switchuser":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "text";
                if ($session['admin']) {
                    $userid = (int) get("userid");
                
                    $result = $mysqli->query("SELECT username FROM users WHERE `id`='$userid'");
                    if ($row = $result->fetch_object()) {
                        $_SESSION['userid'] = $userid;
                        $_SESSION['username'] = $row->username;
                        header("Location: ../?household");
                    }
                }
            }
            break;

        case "admin-sendreport":
            if (!$settings["cydynni"]["is_hub"]) {
                $route->format = "text";
                if ($session['admin']) {
                    require("Lib/email.php");
                    require("Modules/cydynni/cydynni_emails.php");
                    $cydynni_emails = new CydynniEmails($mysqli);
                    return $cydynni_emails->send_report_email(get('userid'));
                }
            }
            break;
            
        case "setupguide":
            header("Location: https://github.com/energylocal/cydynni/blob/master/docs/userguide.md");
            die;
            break;

        // -----------------------------------------------------------------------------------------
        // OTA: Record local hub OTA version and log
        // -----------------------------------------------------------------------------------------
        case "ota":
            if ($session["write"]) {
                 $route->format = "html";
                 $userid = $session["userid"];
                 
                 $result = "<br>";
                 $result .= "<h3>OTA Status</h3>";

                 $r = json_decode($redis->get("cydynni:ota:version:$userid"));
                 if (isset($r->time) && isset($r->hub)) { 
                     $result .= "<p>Hub version <i>(".date("Y-m-d H:i:s",$r->time).")</i>:</p><pre>".$r->hub."</pre>";  
                 }                
                 
                 $r = json_decode($redis->get("cydynni:ota:log:$userid"));
                 if (isset($r->time) && isset($r->log)) { 
                    $result .= "<p>Log output: <i>(".date("Y-m-d H:i:s",$r->time).")</i>:</p>";
                    $result .= "<pre>".$r->log."</pre>";
                 }
            }
            break;
        
        case "ota-version":
             $ota_version = (int) $redis->get("otaversion");
             
             // Record local hub ota version
             if (isset($_GET['hub']) && $session["write"]) {
                 $userid = $session["userid"];
                 $redis->set("cydynni:ota:version:$userid",json_encode(array(
                     "time"=>time(),
                     "hub"=> (int) $_GET['hub'],
                     "master"=>$ota_version
                 )));
             }
             
             $route->format = "text";
             $result = $ota_version;
             break;

        case "ota-version-get":
            if ($session["write"]) {
                 $route->format = "json";
                 $userid = $session["userid"];
                 $result = json_decode($redis->get("cydynni:ota:version:$userid"));
            }
            break;
             
        case "ota-log-set":
            if ($session["write"]) {
                 $userid = $session["userid"];
                 $redis->set("cydynni:ota:log:$userid",json_encode(array(
                     "time"=>time(),
                     "log"=>file_get_contents('php://input')
                 )));
                 return "ok";
            }
            break;
            
        case "ota-log-get":
            if ($session["write"]) {
                 $route->format = "json";
                 $userid = $session["userid"];
                 $result = json_decode($redis->get("cydynni:ota:log:$userid"));
            }
            break;        
        /*
        case "admin":
            if($session["admin"]){
                //get single user
                if ($route->subaction=='users') {
                    //get/set group users
                    //users CRUD                    
                    $route->format = "json";
                    if ($route->method=="POST") {
                        //CREATE USER
                        $returned = $user->register($_POST['username'], $_POST['password'], $_POST['email']);
                        if($returned['success']){
                            //cydynni model save
                            $returned2 = $cydynni->saveUser($_POST, $returned['userid']);
                            if($returned2['success'] && ($returned2['affected_rows']>0||!empty($returned2['user_id']>0))){
                                $result = $cydynni->getUsers($returned2['user_id']);
                            }elseif(!$returned2['success'] && $returned2['affected_rows']==0 && empty($returned2['error'])){
                                $result = array('success'=>false,'message'=>'no added rows');
                            }else{
                                $result = array('success'=>false,'message'=>$returned2['error']);
                            }
                        }else{
                            $result = array('success'=>false,'message'=>'error in creating user');
                        }
                    } elseif ($route->method=="GET") {
                        //READ USER
                        $route->format = "json";
                        if(!empty($route->subaction2)){
                            if(is_numeric($route->subaction2)){
                                //identify single user by id
                                $cydynni_users = $cydynni->getUsers($route->subaction2);
                            }else{
                                //identify single club by slug
                                $club = $cydynni->getClubBySlug($route->subaction2);
                                //identify all users by club _id
                                $cydynni_users = $cydynni->getUsersByClub($club['id']);
                            }
                        }else{
                            //get all users
                            $cydynni_users = $cydynni->getUsers();
                        }
                        // add club and emoncms user data
                        foreach ($cydynni_users as $key=>$value) {
                            $cydynni_users[$key]['club'] = $cydynni->getClubs($value['clubs_id']);
                            $cydynni_users[$key]['user'] = $user->get($value['userid']);
                        }
                        $result = $cydynni_users;
                        
                    } elseif ($route->method=="PUT") {
                        //UPDATE USER
                        $userid = put('userid');
                        if(!$userid){
                            $result = array('success'=>false,'message'=>'no userid sent');
                        }else{
                            $data = array(
                                'mpan'=>put('mpan'),
                                'token'=>put('token'),
                                'premisestoken'=>put('premisestoken'),
                                'welcomedate'=>put('welcomedate'),
                                'reportdate'=>put('reportdate'),
                                'clubs_id'=>put('clubs_id')
                            );
                            array_filter($data);
                            $returned = $cydynni->saveUser($data, $userid);
                            if ($returned['success'] && $returned['affected_rows']>0) {
                                //@todo: should i check for changed values?
                                if (put('username')!=put('username-original')) {
                                    $user->change_username($userid,put('username'));
                                }
                                if (put('email')!=put('email-original')) {
                                    $user->change_email($userid,put('email'));
                                }
                                $result = $cydynni->getUsers($userid);
                            }elseif ($returned['affected_rows']==0) {
                                $result = array('success'=>false,'message'=>'no affected rows');
                            }else{
                                $result = array('success'=>false,'message'=>$returned['error']);
                            }
                        }
                    } elseif ($route->method=="DELETE"){
                        //DELETE USER
                        $userid = delete('userid');
                        if($cydynni->deleteUser($userid)){
                            if($user->delete($userid)){
                                return array('success'=>'true', 'message'=>"User $userid Deleted");
                            }
                        }
                    }

                }elseif($route->subaction=='clubs'){
                    //clubs CRUD
                    if($route->method=="POST"){
                        //CREATE CLUB
                        $club = $cydynni->saveClub($_POST);
                        if(!empty($club['success']) && $club['success']){
                            $result = array($club['data']);
                        }else{
                            $result = array('success'=>false, 'message'=>$club['error'], 'params'=>$club['params']);
                        }
                    }elseif($route->method=="GET"){
                        //READ CLUB
                        if(empty($route->subaction2)){
                            //select all clubs
                            return $cydynni->getClubs();
                        }else{
                            //select club by id or slug
                            if(is_numeric($route->subaction2)){
                                $result = $cydynni->getClubs($route->subaction2);
                            }else{
                                $result = $cydynni->getClubBySlug($route->subaction2);
                            }
                        }
                    }elseif($route->method=="PUT"){
                        //UPDATE CLUB
                        $club_id = put('club_id');
                        if($club_id) {
                            $data = array(
                                'name'=>put('name'),
                                'generator'=>put('generator'),
                                'root_token'=>put('root_token'),
                                'api_prefix'=>put('api_prefix'),
                                'languages'=>put('languages'),
                                'generation_feed'=>put('generation_feed'),
                                'consumption_feed'=>put('consumption_feed'),
                                'color'=>put('color'),
                                'id'=>put('id'),
                                'slug'=>put('slug')
                            );
                            array_filter($data);
                            $returned = $cydynni->saveClub($data, $club_id);
                            if(!empty($returned['success']) && $returned['success']){
                                $result = $cydynni->getClubs($returned['data'][0]['club_id']);
                            }else{
                                $result = array('success'=>false,'message'=>$returned['error']);
                            }
                        }else{
                            $result = array('success'=>false,'message'=>'club id not given');
                        }
                    }elseif($route->method=="DELETE"){
                        //DELETE CLUB
                        $club_id = delete('club_id');
                        if($club_id) {
                            if($cydynni->deleteClub($club_id)){
                                return array('success'=>'true', 'message'=>"Club $club_id Deleted");                                
                            }
                        }else{
                            $result = array('success'=>false,'message'=>'club id not given');
                        }
                    }
                    $route->format = "json";
                }else{
                    //show list of clubs 
                    $route->format = "html";
                    return view("Modules/cydynni/admin_view.php", array());
                }
            }else{
                //does not have privilates or may not be logged in
                if(!$route->is_ajax){
                    $route->format = "html";
                }
                return false;
            }
        break;*/
    }
    
    return array("content"=>$result);   
}

function t($s) {
    global $translation,$lang;
    
    if (isset($translation->$lang) && isset($translation->$lang->$s)) {
        echo $translation->$lang->$s;
    } else {
        echo $s;
    }
}

function translate($s,$lang) {
    global $translation;
    
    if (isset($translation->$lang) && isset($translation->$lang->$s)) {
        return $translation->$lang->$s;
    } else { 
        return $s;
    }
}
