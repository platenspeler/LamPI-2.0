<?php 
require_once ( '../config/backend_cfg.php' );
require_once ( '../daemon/backend_lib.php' );
require_once ( '../daemon/backend_sql.php' );

error_reporting(E_ALL);
header("Cache-Control: no-cache");
header("Content-type: application/json");					// Input and Output are XML coded

if (!isset($_SESSION['debug']))	{ $_SESSION['debug']=0; }
if (!isset($_SESSION['tcnt']))	{ $_SESSION['tcnt'] =0; }


/*	======================================================================================	
	Note: Program to test Zwave equipment on Razberry
	Author: Maarten Westenberg

	Version 2.1 : Jul 31, 2014

	This is a supporting file for LamPI-x.x.js front end application

	It is called ONLY at the moment for setting and retrieving setting[] parameters
	in the config screen of the application.
	1. Read a configuration from file
	2. Store a configuration to file
	3. List the skin files in config
	
NOTE:
	Start initiating the database by executing: http://localhost/kaku/backend_sql.php?init=1
	this will initialize the MySQL database as defined below in init_dbase()
	
	======================================================================================	*/


$debug = 1;
$time_now = time();							// Time NOW at this moment of calling
$time_start = $time_now - ( 24*60*60 );		// Time a day ago

$log = new Logging();
$log->lfile($log_dir.'/zway_daemon.log');
$log->lwrite("\n\n---------------------------------- STARTING ZWAVE DAEMON -----------------------------------");
$apperr = "";
$appmsg = "";


// ----------------------------------------------------------------------------------------
// ZWAY_DUMP
//
// Default is to dump all devices. $dev is for later optional use
function zway_dump($ch,$dev) {
	global $time_start;
	global $debug;
	
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Data/'.$time_start,
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}



// ----------------------------------------------------------------------------------------
// ZWAY_DIM_SET
//
function zway_dim_set($ch,$dev,$val)
{
	global $debug;
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelSet/'.$dev.'/0/'.$val,
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output == false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}

// ----------------------------------------------------------------------------------------
// ZWAY_DIM_GET
//
function zway_dim_get($ch,$dev)
{
	global $debug;
	curl_setopt_array (
	  $ch, array (
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchMultilevel.data.level.value',
	  CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output == false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}

// ----------------------------------------------------------------------------------------
// ZWAY_DIM_STAT
//
function zway_dim_stat($ch,$dev)
{
	global $debug;
	curl_setopt_array (
	$ch, array (
	  //CURLOPT_URL => 'http://zwave-controller:8083/ZAutomation/api/v1/devices/'.$dev.':0:37/command/update',
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchMultilevel.data.level.value',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[38].Get()',
	  CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output == false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}

// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_SET
//
function zway_switch_set($ch,$dev,$val)
{
	global $debug;
	if ($val == "0") {
		curl_setopt_array (
		$ch, array (
		CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchBinaryOff/'.$dev.'/0',
		CURLOPT_RETURNTRANSFER => true
		));
	}
	else {
		curl_setopt_array (
		$ch, array (
		CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchBinaryOn/'.$dev.'/0',
		CURLOPT_RETURNTRANSFER => true
		));
	}
	$output = curl_exec($ch);
	if ($output == false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}


// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_GET
//
function zway_switch_get($ch,$dev)
{
	global $debug;
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchBinaryStatus/'.$dev.'/0',
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}

$apperr = "";	// Global Error. Just append something and it will be sent back
$appmsg = "";	// Application Message (from backend to Client)

// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_STAT
//
function zway_switch_stat($ch,$dev)
{
	global $debug;
	curl_setopt_array (
	$ch, array (
	  //CURLOPT_URL => 'http://zwave-controller:8083/ZAutomation/api/v1/devices/'.$dev.':0:37/command/update',
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchMultilevel.data.level.value',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[37].Get()',
	  CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output == false) {
		if ($debug >= 2 ) echo "curl_exec returned false";
		return false;
	}
	else {
		if ($debug >= 2 ) echo "curl_exec returned: ".$output;
		return ($output);
	}
}

// ----------------------------------------------------------------------------------------
// SETUP CURL
//
$log->lwrite("curl_init start ".$time_now,2);
$ch = curl_init();
if ($ch == false) {
	$log->lwrite("curl_init error");
}

// ========================= MAIN MAIN MAIN MAIN MAIN =====================================

$time_now = time();	
//$start_time = @date('[d/M/y, H:i:s]'); echo "Start loop at ".$start_time."\n";


// ----------------------------------------------------------------------------------------
// READ DATABASE
//
$lampi_config = load_database();
$dev_config = $lampi_config['devices'];

// ----------------------------------------------------------------------------------------
// ZWAY CONFIG
//
$zway_config = zway_dump($ch,0);
$zparse = json_decode($zway_config, true);

$log->lwrite("count config: ".count($dev_config),2);
// $log->lwrite("Dumping zway config:: ".$zway_config."\n");

// ----------------------------------------------------------------------------------------
// LOOP DAEMON
//
for ($i=0; $i < count($dev_config); $i++) 
{
	$id = $dev_config[$i]['id'];
	if ($dev_config[$i]['brand'] == "7" ) 
	{
		//$unit = $dev_config[$i]['unit'];
		$gaddr = $dev_config[$i]['gaddr'];
		$val = $dev_config[$i]['val'];
		$name = $dev_config[$i]['name'];
		$type = $dev_config[$i]['type'];
		$v = "";
		$d = "";
		if ($type == "switch") 
		{
			$d = substr($id, 1);
			$ret = zway_switch_stat($ch,$d);
			$v = zway_switch_get($ch,$d);
			$log->lwrite("zway_switch_get:: returned: <".$v.">",2);
			if ($v == "\"off\"") $v= "0"; else $v= "1";
			if ($v != $val) {
				
				$log->lwrite("Switch update necessary");
				$msg = "!R".$dev_config[$i]['room']."D".$d."F".$v ;
				$log->lwrite ("Message to send: ".$msg);
				send_2_daemon($msg);
			}
		}
		else 
		{
			$d = substr($id, 1);
			$ret = zway_dim_stat($ch,$d);
			// Get the Zwave value and normalize to LamPI values 1-32
			$v = ceil(zway_dim_get($ch,$d)/99*32);
			
			
			if ($v != $val) {
				$log->lwrite("zway_dim_get:: returned: ".$v,2);
				$log->lwrite("Dimmer update necessary with value: ".$v);
				$msg = "!R".$dev_config[$i]['room']."D".$d."FdP".$v ;
				$log->lwrite ("Message to send: ".$msg);
				send_2_daemon($msg);
			}
			//echo "Appmsg: ".$appmsg."\n";
			//echo "Apperr: ".$apperr."\n\n";
		}
		$log->lwrite ("Done zwave device name: ".$name.", id: ".$id.", gaddr: ".$gaddr.", LamPI val: ".$val.", zway val: ".$v);
	}
	else {
		if ($debug >= 2) echo "Not Found zwave device id: ".$id."\n";
	}
}

//$v = zway_dim_get ($ch,3);
//$log->lwrite("Start looking for and update function, zway_dim_get : ".$v);
//$v = ceil($zparse["devices."."3".".instances.0.commandClasses"]["38"]["data"]["level"]["value"]);
//$v = ceil($zparse["devices"]["3"]["instances"]["0"]["commandClasses"]["38"]["data"]["level"]["value"]);
//$v = ceil($zparse["devices.".$d.".instances.0.commandClasses"]["38"]["data"]["level"]["value"]/99*32);
//$log->lwrite("Start looking for and update function, zparse       : ".$v);



//$v = zway_dim_stat ($ch,3);
//$log->lwrite("Start looking for and update function, zway_dim_stat: ".$v);
//$v = zway_dim_get ($ch,3);
//$log->lwrite("Start looking for and update function, zway_dim_get : ".$v);


//$stop_time = @date('[d/M/y, H:i:s]'); echo "Stop loop at ".$stop_time."\n";
//echo "Total time: ".(time()-$time_now)."\n";

flush();

?>