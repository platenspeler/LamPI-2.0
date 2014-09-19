<?php 
require_once ( '../daemon/backend_cfg.php' );
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
	
	======================================================================================	*/

$interval=10;								// Configurable time interval between polls
$debug = 1;									// level 1== only errors
$myIP="192.168.2.53";						// XXX We shoudl make this dynamic


$time_now = time();							// Time NOW at this moment of calling
$time_start = $time_now - ( 24*60*60 );		// Time a day ago

$log = new Logging();
$log->lfile($log_dir.'/LamPI-gate.log');
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
$zway_val = "";
$zway_old = "";
while (true)
{
  // $lampi_config = load_database();
  // $dev_config = $lampi_config['devices'];

  
  $log->lwrite("------------------------ LOOP ----------------------------------",1);
  for ($i=0; $i < count($dev_config); $i++) 
  {
	if ($dev_config[$i]['brand'] == "7" ) 
	{
		
		$id			= $dev_config[$i]['id'];
		//$unit		= $dev_config[$i]['unit'];
		$gaddr		= $dev_config[$i]['gaddr'];
		$gui_val	= $dev_config[$i]['val'];
		$name  		= $dev_config[$i]['name'];
		$type		= $dev_config[$i]['type'];

		$d = "";
		if ($type == "switch") 
		{
			$log->lwrite("",2);
			$dev_config = load_devices();
			$d = substr($id, 1);
			$zway_old = zway_switch_get($ch,$d);
			if (($ret = zway_switch_stat($ch,$d)) === false ) {				// Test for a change. Otherwise old value might be there
				$log->lwrite("zway_switch_stat returned error",1);
			}
			else {
				$log->lwrite("zway_dim_stat returned: <".$ret.">",2);
			}
			$zway_val = zway_switch_get($ch,$d);
			$log->lwrite("zway_switch_get:: returned: <".$zway_val.">",2);
			if ($zway_val == "\"off\"") $zway_val = "0"; else $zway_val = "1";
			if (($zway_val != $gui_val) && ($zway_val != $zway_old)) 
			{
				$log->lwrite("Switch update necessary");
				$msg = "!R".$dev_config[$i]['room']."D".$d."F".$zway_val ;
				$log->lwrite ("Message to send: ".$msg);
				send_2_daemon($msg, $myIP);									// XXX Does it update lastval as well?
				//$dev_config[$i]['val']=$zway_val;
			}
		}
		
		else // DIMMER
		{
			$log->lwrite("",2);
			$gui_old = $dev_config[$i]['val'];
			$dev_config = load_devices();
			$d = substr($id, 1);
			$zway_old = ceil(zway_dim_get($ch,$d)/99*32);					// Container Value before polling
			
			if (($ret = zway_dim_stat($ch,$d)) === false ) {				// Update Container. Otherwise old value might be there
				$log->lwrite("zway_dim_stat returned error",1);
			}
			else {
				$log->lwrite("zway_dim_stat returned: <".$ret.">",2);
			}
			$zway_val = ceil(zway_dim_get($ch,$d)/99*32);					// read Zwave container value and normalize to LamPI values 1-32
			
			// If only the GUI settings have changed (zway updates are late, but sure come)
			if (($gui_val != $gui_old) && ($zway_val == $zway_old)) {
				// A change made in the GUI, so the device should change automatically by the LamPI-daemon
				$log->lwrite ("GUI change from: ".$gui_old." to: ".$gui_val);
			}
			
			// 
			else if (($zway_val != $gui_val) && ($zway_val != $gui_old) )
			{
				// A Change made with the device switch
				$log->lwrite("Dimmer update with value: ".$zway_val);
				$msg = "!R".$dev_config[$i]['room']."D".$d."FdP".$zway_val ;
				$log->lwrite ("Message to send: ".$msg);
				send_2_daemon($msg, $myIP);									// XXX Does it update lastval as well?
			}
			
			//
			else {
				$log->lwrite("Zway made no changes to dimmer: ".$d,2);
			}
		}
		$log->lwrite("device: ".$name.", id: ".$id.", gaddr: ".$gaddr.", gui old:".$gui_old." , gui val: ".$gui_val.", zway old: ".$zway_old.", zway val: ".$zway_val);
	}
	else {
		if ($debug >= 2) echo "Not Found zwave device id: ".$id."\n";
	}// brand==ZWAVE
  } // for
  sleep($interval);
}// while

flush();

?>