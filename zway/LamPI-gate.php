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

$interval =5;								// Configurable time interval between polls
$debug =1;									// level 1== only errors
$daemonIP ="192.168.2.53";					// XXX We should make this dynamic. IP of our LamPI-daemon
$noError =true;

$time_now = time();							// Time NOW at this moment of calling


$log = new Logging();
$log->lfile($log_dir.'/LamPI-gate.log');
$log->lwrite("\n\n---------------------------------- STARTING ZWAVE DAEMON -----------------------------------");

$apperr = "";	// Global Error. Just append something and it will be sent back
$appmsg = "";	// Application Message (from backend to Client)


// ----------------------------------------------------------------------------------------
// ZWAY_DUMP
//
// Default is to dump all devices. $dev is for later optional use
function zway_dump($ch,$tim) {
	// global $time_start;
	global $debug;
	global $log;
	
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Data/'.$tim,
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_dump:: ERROR: ".curl_error($ch),2);
		return false;
	}
	else {
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
	if ($output === false) {
		$log->lwrite("zway_dim_set:: ERROR: ".curl_error($ch),2);
		return false;
	}
	else {
		return ($output);
	}
}


// ----------------------------------------------------------------------------------------
// ZWAY_DIM_VAL
//
// By default it gets the value of a variable. But the third argument may be different
// indicating another variable we're interested in
//
function zway_dim_val($ch,$dev,$arg='value')
{
	global $debug;
	global $log;
	curl_setopt_array (
	  $ch, array (
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchMultilevel.data.level.'.$arg,
	  CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_dim_val:: ERROR: ".curl_error($ch),2);
		return (false);
	}
	else if ($output === null) {
		$log->lwrite("zway_dim_val:: ERROR: curl_exec(".$arg.") returned null",2);
		return (false);
	}
	else {
		return ($output);
	}
}


// ----------------------------------------------------------------------------------------
// ZWAY_DIM_GET
//
function zway_dim_get($ch,$dev)
{
	global $debug;
	global $log;
	curl_setopt_array (
	$ch, array (
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[38].Get()',
	  CURLOPT_RETURNTRANSFER => true
	));
	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_dim_get:: Get() ERROR: ".curl_error($ch),2);
		return false;
	}

	curl_setopt_array (
	$ch, array (
	  //CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchMultilevelLevel/'.$dev.'/0',
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchMultilevel.data.interviewDone.value',
	  CURLOPT_RETURNTRANSFER => true
	));
	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_dim_get:: interviewDone ERROR: ".curl_error($ch),2);
		return false;
	}
	return ($output);
}


// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_SET
//
function zway_switch_set($ch,$dev,$val)
{
	global $debug;
	global $log;
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
	if ($output === false) {
		$log->lwrite("zway_switch_set:: ERROR: ".curl_error($ch),2);
		return false;
	}
	else {
		return ($output);
	}
}


// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_VAL
//
function zway_switch_val($ch,$dev)
{
	global $debug;
	global $log;
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchBinaryStatus/'.$dev.'/0',
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_switch_val:: ERROR: ".curl_error($ch),2);
		return false;
	}
	else {
		return ($output);
	}
}


// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_GET
//
function zway_switch_get($ch,$dev)
{
	global $debug;
	global $log;
	curl_setopt_array (
	$ch, array (
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[37].Get()',
	  CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_switch_get:: ERROR: ".curl_error($ch),2);
		return false;
	}
	
	curl_setopt_array (
	$ch, array (
	  CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].SwitchBinary.data.interviewDone.value',
	  CURLOPT_RETURNTRANSFER => true
	));
	if ($output === false) {
		$log->lwrite("zway_switch_get:: interviewDone ERROR: ".curl_error($ch),2);
		return false;
	}
	$output = curl_exec($ch);
	return ($output);
}


// ----------------------------------------------------------------------------------------
// ZWAY_MOVE_VAL
//
function zway_move_val($ch,$dev)
{
	global $debug;
	global $log;
	curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.52:8083/ZAutomation/OpenRemote/SwitchBinaryStatus/'.$dev.'/0',
	CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_move_val:: ERROR: ".curl_error($ch),2);
		return false;
	}
	else {
		return ($output);
	}
}

// ========================= MAIN MAIN MAIN MAIN MAIN =====================================
//

// ----------------------------------------------------------------------------------------
// SETUP CURL
//
$log->lwrite("curl_init start ".$time_now,2);
$ch = curl_init();
if ($ch == false) {
	$log->lwrite("curl_init error");
}

// ----------------------------------------------------------------------------------------
//
// Setup timer vars
$time_now = time();	
//$start_time = @date('[d/M/y, H:i:s]'); echo "Start loop at ".$start_time."\n";


// ----------------------------------------------------------------------------------------
// READ DATABASE
//
$lampi_config = load_database();
$dev_config = $lampi_config['devices'];
$log->lwrite("count device config: ".count($dev_config),2);

// ----------------------------------------------------------------------------------------
//  ZWAY CONFIG
//
$zway_config = array ();
$zway_val = "";

// ----------------------------------------------------------------------------------------
// ZWAY DUMP DATA
//

$zway_data = zway_dump($ch,0);
$zparse = json_decode($zway_data, true);
$log->lwrite("DUMP::".$zway_data,3);

// Some stupid local vars
$v = "";

// ----------------------------------------------------------------------------------------
// LOOP DAEMON
//
// Loop forever unless a serious error is found.
//
while ($noError)
{
													
  $zway_data = zway_dump($ch,0);										// Read All.
  $zparse = json_decode($zway_data, true);
  
  $time_now = time();
  $time_start = time() - ( 24*60*60 );									// Time minus a day ago
  
  // Loop based on nr of devices in ZWAY
  $log->lwrite("------------------------ DATA DUMP LOOP ----------------------------------",1);

  $log->lwrite("neighbours: ".$zparse['devices'][1]['data']['neighbours']['value'] , 2);
  $log->lwrite("count devices : ".count($zparse['devices']) , 2);		// Including controller
  
  // At the moment we assume that neighbours contains ALL the nodes in the network
  // of the controller
  for ($i=0; $i < count($zparse['devices'][1]['data']['neighbours']['value']); $i++) {
	  
	  $log->lwrite("neighbour: ".$zparse['devices']['1']['data']['neighbours']['value'][$i] , 2);
	  $d = $zparse['devices']['1']['data']['neighbours']['value'][$i];
	  
	  //$log->lwrite("\tneighbour: ".$zparse['devices'][$d]['instances'][0]['commandClasses']['49']['name'] , 2);
	  
	  if ($zparse['devices'][$d]['instances'][0]['commandClasses']['48']['name'] == "SensorBinary" ) {
			$log->lwrite("\tData Dump:: Work on SensorBinary device: ".$d);
			
	  }
	  
	  if ($zparse['devices'][$d]['instances'][0]['commandClasses']['49']['name'] == "SensorMultilevel" ) {
			$log->lwrite("\tData Dump:: Work on SensorMultilevel device: ".$d);
			$log->lwrite("\tTemperature  Val:: ".$zparse['devices'][$d]['instances'][0]['commandClasses']['49']['data']['1']['val']['value']);
			$log->lwrite("\tLuminiscence Val:: ".$zparse['devices'][$d]['instances'][0]['commandClasses']['49']['data']['3']['val']['value']);
			
			curl_setopt_array (
				$ch, array (
	  			CURLOPT_URL => 'http://192.168.2.52:8083/ZWaveAPI/Run/devices['.$d.'].instances[0].commandClasses[49].Get()',
	  			CURLOPT_RETURNTRANSFER => true
			));

			$output = curl_exec($ch);
			if ($output === false) {
				$log->lwrite("zway_switch_get:: ERROR: ".curl_error($ch),2);
				return false;
			}
			
			
	  }//if
  }//for

  // Loop based on $devices array of LAMPI
  //
  $log->lwrite("------------------------ SWITCH/DIM LOOP ----------------------------------",1);
  $log->lwrite("",2);
  $log->lwrite("Count load_devices:: ".count($dev_config),2);
  
  if (count($dev_config) == 0) {
	$log->lwrite("LamPI-gate:: ERROR: No devices read, need to restart MySQL connection");
	$lampi_config = load_database();							// whole database
	$dev_config = $lampi_config['devices'];
  };
  
  $dev_config = load_devices();
  
  // Looping through all devices
  for ($i=0; $i < count($dev_config); $i++) 
  {
	if ($dev_config[$i]['brand'] == "7" ) 
	{
		// If key does not yet exist, create a record for zway
		if ( !array_key_exists($i, $zway_config)) {
			
			$zway_config[$i] = array (
				'id'		=> $dev_config[$i]['id'],
				'gui_inValid'	=> 3,
				'gui_old'	=> $dev_config[$i]['val'],
				'gui_val'	=> $dev_config[$i]['val'],
				//'unit'	=> $dev_config[$i]['unit'],
				'gaddr'		=> $dev_config[$i]['gaddr'],
				'name'  	=> $dev_config[$i]['name'],
				'type'		=> $dev_config[$i]['type']
			);
		}
		
		//$dev_config = load_devices();							// Get latest GUI/MySQL values
		$zway_config[$i]['gui_old'] = $zway_config[$i]['gui_val'];
		$zway_config[$i]['gui_val']	= $dev_config[$i]['val'];
		$gui_val					= $dev_config[$i]['val'];
		$id							= $dev_config[$i]['id'];
		$d 							= substr($id, 1);
		
		// Debug Info
		$log->lwrite(" ",2);
		$log->lwrite("DEVICE: ".$id,2);
		
		// Needed: $ch, $d, $zway_val, $zway_config, $gui_val (*)
		switch ($dev_config[$i]['type']) {
								 
		// DIMMER						 
		case "dimmer":
			// have Get() Update the Container. Otherwise old value might be there
			if (($ret = zway_dim_get($ch,$d)) === false ) { 
				$log->lwrite("zway_dim_get returned error",1); 
			}
			else if ( $ret === 'true' ) {
				$log->lwrite("zway_dim_get returned true",2);
			}
			else { 
				$log->lwrite("\tzway_dim_get returned: <".$ret.">",1); 
				
				$info = curl_getinfo($ch);
				if (!empty($info['http_code'])) {
					if ($info['http_code'] == 500 ) {
						$log->lwrite("\t*** zway_dim_get returned http code 500",1);
						sleep($interval);
						continue;
					}
				}
			}
			
			// read Zwave container value 
			if (false === ( $vv = zway_dim_val($ch,$d) )) {
				$log->lwrite("ERROR: zway_dim_val for id ".$d." returned false");
				$noError=false;
			}
			// Could be a 500 error, timeout etc. that is considered a successful result value
			// Make no change to the value of $zway_val if the result is not numerical
			//else if (!is_numeric($vv)) {
			//	$log->lwrite("ERROR: zway_dim_val returned non numeric value: ".$vv);
			//}
			// Default: zway_dim_val returned a http value
			else {
				$info = curl_getinfo($ch);
				if (!empty($info['http_code'])) {
					switch ($info['http_code'] ) {
						// Code OK
						case 200:
							$log->lwrite("\tzway_dim_val returned http code 200, <OK>",2);
							$zway_val = ceil($vv/99*32);
						break;
						// Internal Error
						case 500:
							// This could be a sign of degrading webserver performance
							$log->lwrite("\t*** zway_dim_val returned http code 500, internal error",1);
							sleep($interval);
							continue;
						break;
						default:
							$log->lwrite("\t*** zway_dim_val returned http code ".$info['http_code'],1);
							sleep($interval);
							continue;
					}
				}
				else {
					$log->lwrite("\tzway_dim_get returned value ".$vv,1);
					//  normalize to LamPI values 1-32
					$zway_val = ceil($vv/99*32);
				}
			}

			// Timing is an issue here, as the update from GUI can be delayed too. 
			// But we expect that if both are not equal than this is a manual/user switch action
			
			// If the value did  change in the last interval, we have a changed GUI value
			if (( $zway_config[$i]['gui_old'] != $gui_val ) && ( $gui_val != $zway_val )) {
				$log->lwrite("Set inValid:: gui_old: ".$zway_config[$i]['gui_old'].", gui_val: ".$gui_val.", zway_val: ".$zway_val);
				$zway_config[$i]['gui_inValid'] = 3;
			}
			else
			//
			// if (( $zway_config[$i]['gui_old'] == $gui_val ) && ( $gui_val == $zway_val )) {
			if (( $gui_val == $zway_val )) {
				// The values are valid, make value 0 to mark
				$zway_config[$i]['gui_inValid'] = 0 ;	
			}
			//
			else {
				// Unknown State, decrease counter
				$log->lwrite("Undefined inValid:: gui_old: ".$zway_config[$i]['gui_old'].", gui_val: ".$gui_val.", zway_val: ".$zway_val,1);
				// XXX But also, check whether the invalidateTime > 2 * $interval
				$log->lwrite("\tupdateTime is ".(time() - zway_dim_val($ch,$d,'updateTime'))." seconds ago",1);
				$log->lwrite("\tinvalidateTime is ".(time() - zway_dim_val($ch,$d,'invalidateTime'))." seconds ago",1);
				$zway_config[$i]['gui_inValid']--; 
			}
			
			// If the zway value did change but we have a valid guis value, this is a manual user action
			// on the device. Send an update to the GUI and the database
			if (( $zway_val != $gui_val) && ($zway_config[$i]['gui_inValid'] <= 0))
			{
				// A Change made with the device switch
				$log->lwrite("\tDimmer update with value: ".$zway_val,1);
				$msg = "!R".$dev_config[$i]['room']."D".$d."FdP".$zway_val ;
				$log->lwrite ("\tMessage to send: ".$msg,2);
				send_2_daemon($msg, $daemonIP);									// XXX Does it update lastval as well?
			}
			//
			else {
				$log->lwrite("\tZway made no changes to dimmer: ".$d,3);
			}
		break;
		
		// SWITCH
		case "switch":

			if (($ret = zway_switch_get($ch,$d)) === false ) {				// Test for a change. Otherwise old value might be there
				$log->lwrite("\tzway_switch_get returned error",1);
			}
			else { 
				$log->lwrite("\tzway_switch_get returned: <".$ret.">",2); 
			}
			
			// Read the new Zway container value
			$zway_val = zway_switch_val($ch,$d);
			$log->lwrite("\tzway_switch_val:: returned: <".$zway_val.">",2);
			
			
			if ($zway_val == "\"off\"") $zway_val = "0"; 
			else if ($zway_val == "\"on\"") $zway_val = "1";
			else $log->lwrite("\tzway_switch_val:: Value not used",2);
			
			// If the GUI value did  change in the last interval, we do not have a true value
			// So invalidate all readings for the moment
			if (( $zway_config[$i]['gui_old'] != $gui_val ) && ( $gui_val != $zway_val )) {
				$zway_config[$i]['gui_inValid'] = 3;
			}
			else
			// All values are stable, validate the value
			if (( $gui_val == $zway_val )) {
				$zway_config[$i]['gui_inValid'] = 0;	
			}
			//
			else {
				$log->lwrite("Undefined state inValid:: gui_old: ".$zway_config[$i]['gui_old'].", gui_val: ".$gui_val.", zway_val: ".$zway_val);
				$zway_config[$i]['gui_inValid']-- ;
			}
		
			// If the zway value changes but the gui value is valid::: We have a manual user action on the device
			if (( $zway_val != $gui_val) && ($zway_config[$i]['gui_inValid'] <= 0))
			{
				$log->lwrite("\rSwitch update with value ".$zway_val);
				$msg = "!R".$dev_config[$i]['room']."D".$d."F".$zway_val ;
				$log->lwrite ("\tMessage to send: ".$msg,2);
				send_2_daemon($msg, $daemonIP);									// XXX Does it update lastval as well?
			}
			//
			else {
				$log->lwrite("\tZway made no changes to switch: ".$d,3);
			}
		break;
		
		case "sensor":
				$log->lwrite("\tZway sensor not yet implemented: ".$d,3);
		break;
		
		default:
			$log->lwrite("\tZway unknown type: ".$dev_config[$i]['type'],2);
		
		}// switch

		// Print the status
		$log->lwrite("device: ".$dev_config[$i]['name'].", id: ".$id.": gui old: ".$zway_config[$i]['gui_old'].", gui val: ".$gui_val.", zway val: ".$zway_val.", inValid: ".$zway_config[$i]['gui_inValid'],2);
	}
	
	else {
	
	}// brand==ZWAVE
  } // for
  sleep($interval);
}// while

flush();

?>