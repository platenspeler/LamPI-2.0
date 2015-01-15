<?php 
require_once ( dirname(__FILE__) . '/../config/backend_cfg.php' );
require_once ( dirname(__FILE__) . '/../daemon/backend_lib.php' );
require_once ( dirname(__FILE__) . '/../daemon/backend_sql.php' );

error_reporting(E_ALL);
header("Cache-Control: no-cache");
header("Content-type: application/json");					// Input and Output are XML coded

if (!isset($_SESSION['debug']))	{ $_SESSION['debug']=0; }
if (!isset($_SESSION['tcnt']))	{ $_SESSION['tcnt'] =0; }

/*	======================================================================================	
	Note: Program to test Zwave equipment on Razberry
	Author: Maarten Westenberg

	Version 2.1 : Jul 31, 2014
	Version 2.4 : Dec 10, 2014

	This is a supporting file for LamPI-x.x.js front end application

	It is called ONLY at the moment for setting and retrieving setting[] parameters
	in the config screen of the application.
	1. Read a configuration from file
	2. Store a configuration to file
	3. List the skin files in config
	
	======================================================================================	*/

set_time_limit(0);							// NO execution time limit imposed
ob_implicit_flush();

$debug = 0;									// level 1== only errors
$interval = 15;								// Interval in seconds
$rinterval = 15;							// resilience interval (how long to wait before all has settled)
$binterval = 300;							// Wakeup every xxx seconds for B attery devices

$razberry = "localhost";					// Configurable time interval between polls
$daemonIP ="192.168.2.53";					// XXX We should make this dynamic. IP of our LamPI-daemon
$noError =true;
$curl_errs = 0;								// Count the number of curl errors
$curl_msgs = 0;

$time_now = time();							// Time NOW at this moment of calling

// ----------------------------------------------------------------------------------------
//  ZWAY CONFIGURATIONS
//
$zway_dev = array ();
$zway_rules = array (
				'id'		=> 1,
				'dev'	=> 9,
				'gui_inValid'	=> 3,
				'rules' => ""
				
				//'gui_old'	=> $dev_config[$i]['val'],
				//'gui_val'	=> $dev_config[$i]['val'],
				//'unit'	=> $dev_config[$i]['unit'],
				//'gaddr'		=> $dev_config[$i]['gaddr'],
				//'name'  	=> $dev_config[$i]['name'],
				//'type'		=> $dev_config[$i]['type']
			);
$zway_val = "";


$log = new Logging();
$log->lfile($log_dir.'/LamPI-gate.log');
$log->lwrite("\n\n---------------------------------- STARTING ZWAVE DAEMON -----------------------------------");

$apperr = "";	// Global Error. Just append something and it will be sent back
$appmsg = "";	// Application Message (from backend to Client)


// ----------------------------------------------------------------------------------------
// ZWAY_DUMP
//
// Default is to dump all devices. $tim is for later optional use
//
function zway_dump($ch,$tim) {
	// global $time_start;
	global $razberry;
	global $debug;
	global $log;
	
	curl_setopt_array (
		$ch, array (
		CURLOPT_URL => 'http://'.$razberry.':8083/ZWaveAPI/Data/'.$tim,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_RETURNTRANSFER => true
	));
	
	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("zway_dump:: ERROR: ".curl_error($ch)." ".$razberry,0);
		// XXX does not work satisfactory
		//$ret .= system('sudo /etc/init.d/z-way-server restart');
		//$log->lwrite("zway_dump:: system returns: ".$ret,1);
		return false;
	}
	else {
		$info = curl_getinfo($ch);
		if (!empty($info['http_code'])) {
			switch ($info['http_code'] ) {
				// Code OK
				case 200:
					$log->lwrite("zway_dump:: http code 200, <OK>",2);
					return($output);
				break;
				// Internal Error
				case 500:
					// This could be a sign of degrading webserver performance
					$log->lwrite("ERROR zway_dump ".$tim." returned http code 500, internal error",0);
					return(false);
				break;
				default:
					$log->lwrite("ERROR zway_dump returned http code ".$info['http_code'],0);
					return(false);
				break;
			}
		}
		else {
			$log->lwrite("zway_dump returned OK",2);
			//  normalize to LamPI values 1-32
			return($output);
		}
	}
}



// ----------------------------------------------------------------------------------------
// ZWAY_DIM_SET
//
function zway_dim_set($ch,$dev,$val)
{
	global $razberry;
	global $debug;
	
	curl_setopt_array (
		$ch, array (
		CURLOPT_URL => 'http://'.$razberry.':8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[38].Set('.$val.')',
		//CURLOPT_URL => 'http://'.$razberry.':8083/ZAutomation/OpenRemote/SwitchMultilevelSet/'.$dev.'/0/'.$val,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_RETURNTRANSFER => true
	));

	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("ERROR zway_dim_set:: ".curl_error($ch),0);
		return false;
	}
	else {
		return ($output);
	}
}



// ----------------------------------------------------------------------------------------
// ZWAY_SWITCH_SET
//
function zway_switch_set($ch,$dev,$val)
{
	global $razberry;
	global $debug;
	global $log;
	if ($val == "0") {
	}
	else {
	}
	
	curl_setopt_array (
		$ch, array (
		// CURLOPT_URL => 'http://'.$razberry.':8083/ZAutomation/OpenRemote/SwitchBinaryOn/'.$dev.'/0',
		CURLOPT_URL => 'http://'.$razberry.':8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses[37].Set('.$val.')',
		CURLOPT_TIMEOUT => 30,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_RETURNTRANSFER => true
	));
	$output = curl_exec($ch);
	if ($output === false) {
		$log->lwrite("ERROR zway_switch_set:: ".curl_error($ch),0);
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
	return(zway_get($ch,$dev, 37));
}


// ----------------------------------------------------------------------------------------
// ZWAY_DIM_GET
//
function zway_dim_get($ch,$dev)
{
	return(zway_get($ch, $dev, 38));
}

// ----------------------------------------------------------------------------------------
// ZWAY_GET
//
function zway_get($ch,$dev,$class)
{
	global $razberry;
	global $debug;
	global $log;
	global $curl_msgs, $curl_errs;
	
	$curl_msgs++;									// Increase curl message counter

	$log->lwrite("zway_get:: Starting for device: ".$dev." class: ".$class,2);
	
	$options = array (
		// Class 37 is for switch, 38 for dimmer, 48 for sensors etc.
		CURLOPT_URL => 'http://'.$razberry.':8083/ZWaveAPI/Run/devices['.$dev.'].instances[0].commandClasses['.$class.'].Get()',
		CURLOPT_TIMEOUT => 30,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_HEADER        => false, 
		//CURLOPT_SSL_VERIFYHOST => 0,				// don't verify ssl
        //CURLOPT_SSL_VERIFYPEER => false,			// 
		CURLOPT_RETURNTRANSFER => true				// Return content as result of this function
	);
	
	if (false === (curl_setopt_array ( $ch, $options) )) {
		$log->lwrite("ERROR zway_get:: curl_setopt_array",0);
		return(false);
	}

	$output = curl_exec($ch);
	if ($output === false) {
		// Most of the times an error occurs because of server not responding
		// in time. Problem is that if a timeout occurs, it makes no sense to send anymore requests with curl
		// until the server internally has cleared its buffer. 
		$log->lwrite("\t*** ERROR zway_get:: ".curl_error($ch),0);
		$curl_errs++;
		return(false);
	}
	else {
		$info = curl_getinfo($ch);
		if (!empty($info['http_code'])) {
			switch ($info['http_code']) {
				// Code OK
				case 200:
					$log->lwrite("\t*** zway_get:: returned http code 200, <OK>",2);
					return(true);
				break;
				case 500:
					// Internal Error, This could be a sign of degrading webserver performance
					// Its is the most often occurring error if we set the timing for curl-exec relaxed.
					$log->lwrite("\t*** ERROR zway_get:: dev: ".$dev.", class: ".$class." http code 500, internal error: ".curl_error($ch),0);
					$curl_errs++;
					return(false);
				break;
				default:
					$log->lwrite("\t*** ERROR zway_get:: http code ".$info['http_code'],0);
					$curl_errs++;
					return(false);
			}
		}
		else {
			$log->lwrite("\t*** ERROR zway_get:: curl_info did not return html_code",0);
		}
	}
	return ($output);
}



// ========================================================================================
//
// =====					 MAIN MAIN MAIN MAIN MAIN 								=======
//
// ========================================================================================

// ----------------------------------------------------------------------------------------
// SETUP CURL
//
$log->lwrite("curl_init start ".$time_now,2);
$ch = curl_init();
if ($ch == false) {
	$log->lwrite("curl_init error",0);
}

// ----------------------------------------------------------------------------------------
//
// Setup timer vars
$time_now = time();	
//$start_time = @date('[d/M/y, H:i:s]'); echo "Start loop at ".$start_time."\n";


// ----------------------------------------------------------------------------------------
// READ DATABASE
//
$pisql = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
$lampi_config = load_database();
//$lampi_config = dbase_parse("load_database","");
$dev_config = $lampi_config['devices'];
$log->lwrite("count device config: ".count($dev_config),2);


// ----------------------------------------------------------------------------------------
// ZWAY DUMP DATA
//

$zway_data = zway_dump($ch,0);
$zway_utf8 = utf8_encode($zway_data);
$zparse = json_decode($zway_utf8, true);
if ($zparse === NULL) {
	$log->lwrite("DUMP:: ERROR: ".json_last_error(),0);
	$noError = false;
}
else {
	$log->lwrite("DUMP::".$zway_data,2);
}

// Some stupid local vars
$v = "";
$oval=true;											// Just init the value



// ----------------------------------------------------------------------------------------
// MAIN LOOP DAEMON
//
// Loop forever unless a serious error is found.
//
while ($noError)
{

  sleep($interval);
  
  // Polling all zway devices for their value 
  //
  $log->lwrite("------------------- ZWAY DUMP LOOP ----------------------",0);												
  if (false === ($zway_data = zway_dump($ch,0))) {
	$log->lwrite("ERROR zway_dump",0);									// Read All.
	sleep(3);
	continue;
  }													

  $zparse = json_decode($zway_data, true);
  
  $time_now   = time();
  $time_start = time() - ( 24*60*60 );									// Time minus a day ago

  // First print come statistics
  $log->lwrite("count devices : ".count($zparse['devices']) , 2);		// Including controller
  $log->lwrite("curl messages: ".$curl_msgs.", errors: ".$curl_errs, 2);
  
  $dkeys = array_keys($zparse['devices']);								// Device keyrs, array containing all device id's
  $log->lwrite("count dkeys : ".count($dkeys), 2);						// Including controller

  
  //
  // $dkeys devices contains ALL the nodes in the network of the controller
  // In order to ONLY query devices start the for loop with index 1
  // 
  for ($i=1; $i < count($dkeys); $i++) {
	
	$d = $dkeys[$i];													// Current Device Key id, id==1 is the console
	// Array of command classes available for this device
	$dd = $zparse['devices'][$d]['instances'][0]['commandClasses'];
	$ckeys = array_keys($dd);											// Commandclasses of device $[$i]
	
	// Loop through all commandclasses and get the values for all 
	// non-battery operated devices
	$classes = "";														// String of commandclasses (debug only)
	for ($j=0; $j< count($ckeys); $j++) {
		if ($debug >= 1) $classes .= ", ".$ckeys[$j];
		if ($debug >= 2) $classes .= ": ".$dd[$ckeys[$j]]['name'];
		// Get the values for the devices
	}
	$log->lwrite("",1);
	$log->lwrite("Device: ".$d.", #ckeys: ".count($ckeys)."".$classes, 1);

	// 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128 128
	// Is the device a Battery
	if ( in_array(128, $ckeys ))  
	{
		$val = $dd['128']['data']['last']['invalidateTime'];
		$log->lwrite("\t128 Battery  Val                 :: ".$dd['128']['data']['last']['value'],1);
		$log->lwrite("\t128 Battery updated              :: ".@date('[d/M/y, H:i:s]',$dd['128']['data']['last']['updateTime']),1);
	}
	else {
		$log->lwrite("Data Dump:: Not an Battery device: ".$d, 3);
	}

	// 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37 37
	// Switch
	if ( in_array(37, $ckeys ))
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 37);
		$val = $dd['37']['data']['level']['value']+0;
		$log->lwrite("\t 37 Switch Val                   :: ".$val,1);
	}
	
	// 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38 38
	// Dimmer
	if ( in_array(38, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 38);
		$log->lwrite("\t 38 Dimmer Val                   :: ".$dd['38']['data']['level']['value'],1);
	}
	
	// 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48 48
	// Is the device a sensor binary (code 48) ?
	// XXX We really need a decent rule engine for this!
	if ( in_array(48, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 48);
		// New current value
		$val  = $dd['48']['data']['1']['level']['value'];				// THis is the PIR alarm value
		$lumi = $dd['49']['data']['3']['val']['value'];					// Need this for a rule
		if ($oval != $val) {
			if  ($val == true) { 										// Motion detected
				//if ( $lumi <= 6 ) {										// And it s rather dark in the room
					// Do some work for this rule
				//	$msg = '!FqP"living on"';							// Hard Coded
				//	send_2_daemon($msg, $daemonIP);
				//}
				$log->lwrite("\t 48 SensorBinary Val (PIR)       :: true",0);
			}
			else {
				$log->lwrite("\t 48 SensorBinary Val             :: false",1);
			}
		}
		else {
				$log->lwrite("\t 48 SensorBinary Val unchanged   :: ".$val,1);	
		}
		$oval = $val;
		// Some more PIR statistics
		$val = $dd['48']['data']['interviewCounter']['value'];
		$log->lwrite("\t 48 SensorBinary interviewCounter:: ".$val,2);
		$val = $dd['48']['data']['1']['level']['updateTime'];
		$log->lwrite("\t 48 SensorBinary updateTime      :: ".@date('[d/M/y, H:i:s]',$val),1);
	}
	else {
		$log->lwrite("Data Dump:: Not a SensorBinary device: ".$d, 3);
	}

	// 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49 49
	// Is the device a multiSensor device with code '49'
	//
	if ( in_array(49, $ckeys )) 
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 49);
		// $dd = $zparse['devices'][$d]['instances'][0]['commandClasses'];
		$log->lwrite("\t 49 MultiSensor Temperature  Val :: ".$dd['49']['data']['1']['val']['value'],1);
		$log->lwrite("\t 49 MultiSensor Luminiscence Val :: ".$dd['49']['data']['3']['val']['value'],1);
	}//if
	else {
		$log->lwrite("\tData Dump:: Not a SensorMultilevel device: ".$d, 3);
	}
	
	// 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67 67
	// Is the device a Thermostat device with code '67'
	//
	if ( in_array(67, $ckeys )) 
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 67);
		// $dd = $zparse['devices'][$d]['instances'][0]['commandClasses'];
		$log->lwrite("\t 67 ThermostatSetPoint  Val      :: ".$dd['67']['data']['1']['val']['value'],1);
		$log->lwrite("\t 67 ThermostatSetPoint setVal    :: ".$dd['67']['data']['1']['setVal']['value'],1);
	}//if
	else {
		$log->lwrite("\tData Dump:: Not a SensorMultilevel device: ".$d, 3);
	}
	
	
	// 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132 132
	// Is the device a Wakeup
	if ( in_array(132, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 132);
		
		// $dd = $zparse['devices'][$d]['instances'][0]['commandClasses'];
		$log->lwrite("\t132 Wakeup   Interval            :: ".$dd['132']['data']['interval']['value'],1);
		$log->lwrite("\t132 Wakeup Last Sleep            :: ".@date('[d/M/y, H:i:s]',$dd['132']['data']['lastSleep']['value']),1);
		$log->lwrite("\t132 Wakeup lastWakeup            :: ".@date('[d/M/y, H:i:s]',$dd['132']['data']['lastWakeup']['value']),1);
	}
	else {
		$log->lwrite("Data Dump:: Not an Wakeup device: ".$d, 3);
	}
	
	// 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133 133
	// Is the device an Association
	if ( in_array(133, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 133);
		$log->lwrite("\t133 Association                  :: ",1);
	}
	else {
		$log->lwrite("Data Dump:: Not an Association device: ".$d, 3);
	}
	
	// 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142 142
	// Is the device a MultiChannelAssociation
	if ( in_array(142, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 142);
		$log->lwrite("\t142 MultiChannelAssociation      :: ",1);
	}
	else {
		$log->lwrite("\tData Dump:: Not an MultiChannelAssociation device: ".$d, 3);
	}
	
	// 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143 143
	// Is the device a MultiChannelAssociation
	if ( in_array(143, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 143);
		$log->lwrite("\t143 MultiCommand                 :: ",1);
	}
	else {
		$log->lwrite("\tData Dump:: Not an MultiChannelAssociation device: ".$d, 3);
	}
	
	// 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156 156
	// AlarmSensor
	if ( in_array(156, $ckeys ))  
	{
		if (! in_array(128, $ckeys) ) zway_get($ch, $d, 156);
		// $dd = $zparse['devices'][$d]['instances'][0]['commandClasses'];
		$log->lwrite("\t156 AlarmSensor  Val             :: ".$dd['156']['data']['0']['sensorState']['value'],1);
	}
	else {
		$log->lwrite("\tData Dump:: Not an AlarmSensor device: ".$d, 3);
	}
	
  }//for



  // ASSUMING WE JUST RENEWED ALL SENSORS IN THE DATA LOOP ABOVE, DOD NOT USE THE GET() FUNCTION BELOW.
  // XXX WE SHOULD PROBABLY INTEGRATE LOOOP BELOW IN THE LOOP ABOVE
  
  // Loop based on $devices array of LAMPI
  //
  $log->lwrite("------------------ SWITCH/DIMMER LOOP ----------------------",1);
  $log->lwrite("",2);
  $log->lwrite("Count load_devices:: ".count($dev_config),2);
  
  if (count($dev_config) == 0) {
	$log->lwrite("ERROR LamPI-gate:: No devices read, need to restart MySQL connection",0);
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
		if ( !array_key_exists($i, $zway_dev)) {
			
			$zway_dev[$i] = array (
				'id'		=> $dev_config[$i]['id'],
				'gui_inValid'	=> 3,
				'gui_old'	=> $dev_config[$i]['val'],
				'gui_val'	=> $dev_config[$i]['val'],
				'unit'		=> $dev_config[$i]['unit'],
				'gaddr'		=> $dev_config[$i]['gaddr'],
				'name'  	=> $dev_config[$i]['name'],
				'type'		=> $dev_config[$i]['type']
			);
		}
		
		//$dev_config = load_devices();							// Get latest GUI/MySQL values
		$zway_dev[$i]['gui_old']	= $zway_dev[$i]['gui_val'];
		$zway_dev[$i]['gui_val']	= $dev_config[$i]['val'];
		$gui_val					= $dev_config[$i]['val'];
		$unit						= $dev_config[$i]['unit'];
		$d 							= substr($dev_config[$i]['id'], 1);
		
		// Debug Info
		$log->lwrite(" ",2);
		$log->lwrite("DEVICE: ".$unit,2);
		
		// Needed: $ch, $unit, $zway_val, $zway_dev, $gui_val (*)
		switch ($dev_config[$i]['type']) {
								 
		// DIMMER						 
		case "dimmer":
			$dd = $zparse['devices'][$unit]['instances'][0]['commandClasses'];
			$updateTime = $dd['38']['data']['level']['updateTime'];
			$updateInterval= time() - $updateTime;
			$vv = $dd['38']['data']['level']['value'];
			$zway_val = ceil($dd['38']['data']['level']['value']/99*32);
			$log->lwrite("\tDimmer ".$unit.":: Raw: ".$vv." Value <".$zway_val.">",0);
			
			if ($zway_val > 32 ) {
				// Impossible Dim value
				$log->lwrite("\tunit: ".$unit.",dimmer ".$zway_dev[$i]['name'].", updateTime:: "
							.@date('[d/M/y, H:i:s]',$updateTime),0);
				$log->lwrite("Dimval inValid:: ".$zway_dev[$i]['name'].": gui_old: ".$zway_dev[$i]['gui_old']
							.", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
				$zway_val = $zway_dev[$i]['gui_old'];
			}
			else {
				// Value is in correct range
				$log->lwrite("\tunit: ".$unit.",dimmer ".$zway_dev[$i]['name']
						.", updateTime:: ".@date('[d/M/y, H:i:s]',$updateTime),1);
			}
			
			// Timing is an issue here, as the update from GUI can be delayed too. 
			// But we expect that if both are not equal than this is a manual/user switch action
			
			// As soon as gui-val and zway-val are equal, no work to be done
			//
			if ( $gui_val == $zway_val ) {
				// The values are valid, make value 0 to mark we're set
				$zway_dev[$i]['gui_inValid'] = 3;	
			}
			
			// If the zway value did change but we have a valid gui value, this is a manual user action
			// on the device. Send an update to the GUI and the database
			else if ( $zway_dev[$i]['gui_inValid'] <= 0 )
			{
				// A Change made with the device switch
				$log->lwrite("\tDimmer update with value: ".$zway_val,0);
				$msg = "!R".$dev_config[$i]['room']."D".$unit."FdP".$zway_val ;
				$log->lwrite ("\tMessage to send: ".$msg,2);
				send_2_daemon($msg, $daemonIP);									// XXX Does it update lastval as well?
			}
			
			// If the GUI value did change in the last interval, we will wait
			// some cycles in order to have the values stabilize and take action
			else if ( $gui_val != $zway_dev[$i]['gui_old'] ) 
			{
				$log->lwrite("Dimval inValid:: ".$zway_dev[$i]['name'].": gui_old: ".$zway_dev[$i]['gui_old']
					.", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
				$zway_dev[$i]['gui_inValid']--;
			}
			// zway-val has changed. OR we loop because the GUI was changed but we wait some cycles.
			// This could be the first time we're here, but could also 
			//
			else 
			{
				// Unknown State, decrease counter. When this happens we wait another cycle
				$log->lwrite("Dim state change:: ".$zway_dev[$i]['name']." gui_old: ".$zway_dev[$i]['gui_old']
					.", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
	
				$log->lwrite("\tupdateTime:: ".@date('[d/M/y, H:i:s]',$updateTime).", ".$updateInterval." seconds ago",1);
				$log->lwrite("\tinvalidateTime is ".(time() - $updateTime)." seconds ago",2);
				$zway_dev[$i]['gui_inValid']--;
				//
				// If the interval is unrealistic, the reported zway value might be in error (internal zway error)
				// In this case we should not decrease the value but set back the zway value to
				// the correct value in the LamPI system
				if ($updateInterval > (3 * $interval)) {
					zway_dim_set($ch,$unit,$zway_dev[$i]['gui_old']);			// Set back to the old value
					$zway_dev[$i]['gui_inValid'] = 0;
					$log->lwrite("ERROR: Dimmer corrected:: ".$zway_dev[$i]['name']." gui_old: " 
						.$zway_dev[$i]['gui_old'].", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
				}
			}
		break;
		
		// SWITCH
		case "switch":
			$dd = $zparse['devices'][$unit]['instances'][0]['commandClasses'];
			$zway_val = $dd['37']['data']['level']['value']+0;
			$updateTime = $dd['37']['data']['level']['updateTime'];
			$updateInterval= time() - $updateTime;
			if ($zway_val == "\"off\"") $zway_val = "0"; 
			else if ($zway_val == "\"on\"") $zway_val = "1";
			//else $log->lwrite("\tSwitch ".$unit.":: Value ".$zway_val." read",0);
			$log->lwrite("\tSwitch ".$unit.":: Raw: ".$zway_val." Value <".$zway_val.">",0);
			
			// As soon as gui-val and zway-val are equal, no work to be done
			//
			if ( $gui_val == $zway_val ) {
				// The values are valid, make value 0 to mark we're set
				$zway_dev[$i]['gui_inValid'] = 3;	
			}
			
			// If the zway value did change but we have a valid gui value, this is a manual user action
			// on the device. Send an update to the GUI and the database
			else if ( $zway_dev[$i]['gui_inValid'] <= 0 )
			{
				// A Change made with the device switch
				$log->lwrite("\tSwitch update with value: ".$zway_val,0);
				$msg = "!R".$dev_config[$i]['room']."D".$unit."F".$zway_val ;
				$log->lwrite ("\tMessage to send: ".$msg,2);
				send_2_daemon($msg, $daemonIP);									// XXX Does it update lastval as well?
			}
			
			// If the GUI value did change in the last interval, we will wait
			// some cycles in order to have the values stabilize and take action
			else if ( $gui_val != $zway_dev[$i]['gui_old'] ) 
			{
				$log->lwrite("Switch val inValid:: ".$zway_dev[$i]['name'].": gui_old: ".$zway_dev[$i]['gui_old']
					.", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
				$zway_dev[$i]['gui_inValid']--;
			}
			// zway-val has changed. OR we loop because the GUI was changed but we wait some cycles.
			// This could be the first time we're here, but could also 
			//
			else 
			{
				// Unknown State, decrease counter. When this happens we wait another cycle
				$log->lwrite("Switch state change:: ".$zway_dev[$i]['name']." gui_old: ".$zway_dev[$i]['gui_old']
					.", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
	
				$log->lwrite("\tupdateTime:: ".@date('[d/M/y, H:i:s]',$updateTime).", ".$updateInterval." seconds ago",1);
				$log->lwrite("\tinvalidateTime is ".(time() - $updateTime)." seconds ago",2);
				$zway_dev[$i]['gui_inValid']--;
				//
				// If the interval is unrealistic, the reported zway value might be in error (internal zway error)
				// In this case we should not decrease the value but set back the zway value to
				// the correct value in the LamPI system
				if ($updateInterval > (3 * $interval)) {
					zway_switch_set($ch,$unit,$zway_dev[$i]['gui_old']);			// Set back to the old value
					$zway_dev[$i]['gui_inValid'] = 0;
					$log->lwrite("ERROR: Switch corrected:: ".$zway_dev[$i]['name']." gui_old: " 
						.$zway_dev[$i]['gui_old'].", gui_val: ".$gui_val.", zway_val: ".$zway_val,0);
				}
			}
		break;
		
		case "sensor":
				$log->lwrite("\tSensor for device id: ".$i." not yet implemented, zway id: ".$unit,2);
		break;
		
		case "thermostat":
				$log->lwrite("\tThermostat for device id: ".$i." not yet implemented, sway id: ".$unit,2);
		break;
		
		default:
			$log->lwrite("\tZway unknown type: ".$dev_config[$i]['type'],1);
		
		}// switch

		// Print the status
		$log->lwrite("device: ".$dev_config[$i]['name'].", unit: ".$unit.": gui old: ".$zway_dev[$i]['gui_old']
			.", gui val: ".$gui_val.", zway val: ".$zway_val.", inValid: ".$zway_dev[$i]['gui_inValid'],2);
	}
	
	else {
	
	}// brand==ZWAVE
  } // for
  
}// while

$log->lwrite("Closing LamPI-gate");
$log->lclose();
flush();

?>