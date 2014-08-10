<?php 
require_once( 'frontend_cfg.php' );

// This file contains a number of supporting functions for the LamPI-daemon.php process.
// - Database Functions
// - Initialization, 
// - Login and Cookies
// - Device Commununication functions

/*
 *************************** DATABASE FUNCTIONS *********************************

  Functions:
	load_database();			return code 1
	store_database; 			return code 2 reserved, not yet implemented 

	add_device($device);		return code 6
	store_device($device);		return code 3 upon success, store new value of a device
	delete_device($device);		return code 4 upon succes, delete complete device
		
	add_room($room)				return code 7
	delete_room($room)			return code 10

	add_scene($scene)			return code 9
	store_scene($scene);		return code 8	When user hits store button after making changes
	delete_scene($scene)		return code 11
	
	add_timer($timer)			return code 12
	store_timer($timer);		return code 13	After making changes
	delete_timer($timer)		return code 14
	
	add_handset($handset);		return code 17
	store_handset($handset);	return code 15
	delete_handset($handset);	return code 16
	
	store_setting($setting);	return code 5 upon success
	
  All functions return -1 upon failue and fill $apperr with some sort of error description
*/


//error_reporting(E_ALL);
error_reporting(E_ERROR | E_PARSE | E_NOTICE);				// For a daemon, suppress warnings!!

header("Cache-Control: no-cache");
header("Content-type: application/json");					// Input and Output are XML coded

session_start();
if (!isset($_SESSION['debug']))	{ $_SESSION['debug']=1; }
if (!isset($_SESSION['tcnt']))	{ $_SESSION['tcnt'] =0; }

// ************************* Supporting Functions *************************


// --------------------------------------------------------------------------------------
// Logging class:
// - contains lfile, lwrite and lclose public methods
// - lfile sets path and name of log file
// - lwrite writes message to the log file (and implicitly opens log file)
// - lclose closes log file
// - first call of lwrite method will open log file implicitly
// - message is written with the following format: [d/M/Y:H:i:s] (script name) message
//
class Logging {

    // declare log file and file pointer as private properties
    private $log_file, $fp;
	
    // set log file (path and name)
    public function lfile($path) {
        $this->log_file = $path;
    }
	
    // write message to the log file
    public function lwrite($message,$dlevel=false) {
		global $debug;
		// If we specify a minimum debug level required to log the message
		if (($dlevel) && ($dlevel>$debug)) return(0);
        // if file pointer doesn't exist, then open log file
        if (!is_resource($this->fp)) {
            $this->lopen();
        }
        // define script name
        $script_name = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
        // define current time and suppress E_WARNING if using the system TZ settings
        // (don't forget to set the INI setting date.timezone)
        $time = @date('[d/M/y, H:i:s]');
        // write current time, script name and message to the log file
        fwrite($this->fp, "$time ($script_name) $message" . PHP_EOL);
    }
	
    // close log file (it's always a good idea to close a file when you're done with it)
    public function lclose() {
        fclose($this->fp);
    }
	
    // open log file (private method)
    private function lopen() {
        // in case of Windows set default log file
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $log_file_default = 'c:/php/logfile.txt';
        }
        // set default log file for Linux and other systems
        else {
            $log_file_default = '/tmp/logfile.txt';
        }
        // define log file from lfile method or use previously set default
        $lfile = $this->log_file ? $this->log_file : $log_file_default;
        // open log file for writing only and place file pointer at the end of the file
        // (if the file does not exist, try to create it)
        $this->fp = fopen($lfile, 'a') or exit("Can't open $lfile!");
    }
}

// ------------------------------------------------------------------------
// Handle Comments is any
//
function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0) {
    // search and remove comments like /* */ and //
    $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
   
    if(version_compare(phpversion(), '5.4.0', '>=')) {
        $json = json_decode($json, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) {
        $json = json_decode($json, $assoc, $depth);
    }
    else {
        $json = json_decode($json, $assoc);
    }

    return $json;
}



/*	--------------------------------------------------------------------------------	
	function decho. 
	Subsitute of echo function. Does only print if the level 
	specified is larger than the value in the session var.
	Session var can be set on URL with yoursite.com?debug=2
	--------------------------------------------------------------------------------	*/
function decho($str,$lev=2) 
{
	global $apperr;
	
	if ($_SESSION['debug'] >= $lev ) {
//		echo $str;
		$apperr .= $str."\n";
	}
}


// ---------------------------------------------------------------------------------
// load_database()
//
// Load the complete database from mySQL into ONE $config object!
// 
// NOTE: This function is VERY sensitive to the right fields of the objects etc.
//		So make sure you have exactly the right number of argument and if you change
// the record/object definition in the configuration object, make sure that MySQL
// follows (frontend_set.php)
//
function load_database()
{
	// We assume that a database has been created by the user. host/name/passwd in backend_cfg.php
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	$config = array();
	$devices = array();
	$rooms = array();
	$scenes = array();
	$timers = array();
	$handsets = array();
	$settings = array();
	$controllers = array();
	$brands = array();
	$weather = array();
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL on host ".$dbhost." (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return(-1);
	}
	//mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, gaddr, room, name, type, val, lastval, brand FROM devices";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$devices[] = $row ;
	}
	mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, name FROM rooms";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$rooms[] = $row ;
	}
	mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, val, name, seq FROM scenes";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$scenes[] = $row ;
	}
	mysqli_free_result($query);

	$sqlCommand = "SELECT id, name, scene, tstart, startd, endd, days, months, skip FROM timers";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$timers[] = $row ;
	}
	mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, val, name FROM settings";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$settings[] = $row ;
	}
	mysqli_free_result($query);	
	
	$sqlCommand = "SELECT id, name, brand, addr, unit, val, type, scene FROM handsets";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$handsets[] = $row ;
	}
	mysqli_free_result($query);

	$sqlCommand = "SELECT id, name, fname FROM controllers";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$controllers[] = $row ;
	}
	mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, name, fname FROM brands";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$brands[] = $row ;
	}
	mysqli_free_result($query);
	
	$sqlCommand = "SELECT id, name, location, brand, address, channel, temperature, humidity, airpressure, windspeed, winddirection rainfall FROM weather";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$weather[] = $row ;
	}
	mysqli_free_result($query);
	
	$config ['rooms']   = $rooms;
	$config ['devices'] = $devices;
	$config ['scenes']  = $scenes;
	$config ['timers']  = $timers;
	$config ['handsets']  = $handsets;
	$config ['settings']= $settings;
	$config ['controllers']= $controllers;
	$config ['brands']= $brands;
	$config ['weather']= $weather;
	
	mysqli_close($mysqli);
	$apperr = "";										// No error
	return ($config);
}


/*	-----------------------------------------------------------------------------------	
	The function received/reads a jSon message from the client
	side and decodes it. After decoding, the resulting
	datastructure will be written to file.
	The datastructure describes the configuration of the
	ICS-1000/PI controller
	----------------------------------------------------------------------------------	*/
function store_database($inp)
{
	$dec = json_decode($inp);
	return(2);	
}


// ----------------------------------------------------------------------------------
//
// Store a device object as received from the ajax call and update mySQL
//
function store_device($device)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "store_device:: device id: ".$device[id]." room: ".$device[room]." val: ".$device[val]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (".$mysqli->connect_errno.") ".$mysqli->connect_error, 1);
		return (-1);
	}
	
	// Update the database
	if (!mysqli_query($mysqli,"UPDATE devices SET gaddr='{$device[gaddr]}', val='{$device[val]}', lastval='{$device[lastval]}', name='{$device[name]}', brand='{$device[brand]}' WHERE room='$device[room]' AND id='$device[id]'" ))
	{
		$apperr .= "mysqli_query error" ;
//		$apperr .= "mysqli_query Error: " . mysqli_error($mysqli) ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "store_device successful\n" ;
	return(3);
}

/*	-----------------------------------------------------------------------------------
	Delete a device record from the database. This is one of the element functions
	needed to synchronize the database with the memory storage in the client, and
	prevents information loss between reloads of the screen.
	
	-----------------------------------------------------------------------------------	*/
function delete_device($device)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr = "delete_device:: id: ".$device[id]." room: ".$device[room]." val: ".$device[val]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
	}
	
	$msg = "DELETE FROM devices WHERE id='$device[id]' AND room='$device[room]'";
	$apperr .= $msg;
	if (!mysqli_query($mysqli, "DELETE FROM devices WHERE id='$device[id]' AND room='$device[room]'" ))
	{
		$apperr .= "mysqli_query error" ;

	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "delete_device successful\n" ;
	return(4);
}

// ----------------------------------------------------------------------------------
//
// Add a device object as received from the ajax call and update mySQL
//
// ----------------------------------------------------------------------------------- */
function add_device($device)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr = "add_device:: id: ".$device[id]." room: ".$device[room]." val: ".$device[val]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (".$mysqli->connect_errno.") ".$mysqli->connect_error,1);
		return (-1);
	}
	
	if (!$mysqli->query("INSERT INTO devices (id, gaddr, room, name, type, val, lastval, brand) VALUES ('" 
							. $device[id] . "','" 
							. $device[gaddr] . "','"
							. $device[room] . "','"
							. $device[name] . "','"
							. $device[type] . "','"
							. $device[val] . "','"
							. $device[lastval] . "','"
							. $device[brand] . "')"
							) 
			)
	{
		$apperr .= "mysqli_query INSERT error(" . $mysqli->errno . ") " . $mysqli->error . "\n" ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "add_device successful\n" ;
	return(6);
}


// ----------------------------------------------------------------------------------
//
// Add a room object as received from the ajax call and update mySQL
//
// ----------------------------------------------------------------------------------- */
function add_room($room)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr = "add_room:: id: ".$room[id]." name: ".$room[name]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
	
	if (!$mysqli->query("INSERT INTO rooms (id, name) VALUES ('" 
							. $room[id]	. "','" 
							. $room[name] . "')"
							) 
			)
	{
		$apperr .= "mysqli_query INSERT error(" . $mysqli->errno . ") " . $mysqli->error . "\n" ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "add_room successful\n" ;
	return(7);
}

/*	-----------------------------------------------------------------------------------
	Delete a room record from the database. This is one of the element functions
	needed to synchronize the database with the memory storage in the client, and
	prevents information loss between reloads of the screen.
	
	-----------------------------------------------------------------------------------	*/
function delete_room($room)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "room id: " . $room[id] . " name: " . $room[name]  . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
	}
	
	$msg = "DELETE FROM rooms WHERE id='$room[id]' ";
	$apperr .= $msg;
	if (!mysqli_query($mysqli, "DELETE FROM rooms WHERE id='$room[id]' " ))
	{
		$apperr .= "mysqli_query error" ;

	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "delete_room successful\n" ;
	return(10);
}


//	--------------------------------------------------------------------------------
//	Function read scene from MySQL
//
//	Lookup the scene with the corresponding name
//	-----------------------------------------------------------------------------------
function read_scene($name)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	$res = array();
	// We need to connect to the database for start

	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
	}
	
	$sqlCommand = "SELECT id, val, name, seq FROM scenes WHERE name='$name' ";
	//$sqlCommand = "SELECT seq FROM scenes WHERE name='$name' ";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$res[] = $row ;
	}

	mysqli_free_result($query);
	mysqli_close($mysqli);
	
	// NOTE: Assuming every sequence/scene name is unique, we return ONLY the first scene
	//	remember for seq only to use result['seq'] for sequence only
	if (count($res) == 0) {
		$apperr .= "ERROR read_scene: scene $name not found\n";
		return(-1);
	}
	else {
		// Only return ONE scene (there should only be one btw)
		return ($res[0]);
	}
}

// ----------------------------------------------------------------------------------
//
// Add a scene object as received from the ajax call and update mySQL
//
// ----------------------------------------------------------------------------------- */
function add_scene($scene)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "scene id: " . $scene[id] . " name: " . $scene[name] . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
	
	if (!$mysqli->query("INSERT INTO scenes (id, val, name, seq) VALUES ('" 
							. $scene[id] . "','" 
							. $scene[val]. "','"
							. $scene[name]. "','"
							. $scene[seq]. "')"
							) 
			)
	{
		$apperr .= "mysqli_query INSERT error(" . $mysqli->errno . ") " . $mysqli->error . "\n" ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "add_scene successful\n" ;
	return(9);
}


//	-----------------------------------------------------------------------------------
//	Store the scene record in the MySQL database
//	
//	-----------------------------------------------------------------------------------
function store_scene($scene)
{	
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "Scene id: ".$scene[id]." name: ".$scene[name]." val: ".$scene[val].", seq".$scene[seq]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") ".$mysqli->connect_error , 1);
		return (-1);
	}
//
	$test = "UPDATE scenes SET val='{$scene[val]}', name='{$scene[name]}', seq='{$scene[seq]}' WHERE id='$scene[id]' ";
	$apperr .= $test;
	if (!mysqli_query($mysqli,"UPDATE scenes SET val='{$scene[val]}', name='{$scene[name]}', seq='{$scene[seq]}' WHERE  id='$scene[id]' " ))
	{
		$apperr .= "Error: Store scene, ";
		$apperr .= "mysqli_query error" ;
	//		apperr .= "mysqli_query Error: " . mysqli_error($mysqli) ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "store_scene successful\n" ;
	return(8);
}


//	-----------------------------------------------------------------------------------
//	Delete a scene record from the database. This is one of the element functions
//	needed to synchronize the database with the memory storage in the client, and
//	prevents information loss between reloads of the screen.
//	
//	-----------------------------------------------------------------------------------
function delete_scene($scene)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "scene id: " . $scene[id] . " name: " . $scene[name]  . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
	}
	
	$msg = "DELETE FROM scenes WHERE id='$scene[id]' ";
	$apperr .= $msg;
	if (!mysqli_query($mysqli, "DELETE FROM scenes WHERE id='$scene[id]' " ))
	{
		$apperr .= "mysqli_query error" ;

	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "delete_scene successful\n" ;
	return(11);
}

// ----------------------------------------------------------------------------------
//
// Add a timer object as received from the ajax call and update mySQL
//
// ----------------------------------------------------------------------------------- */
function add_timer($timer)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "timer id: " . $timer[id] . " name: " . $timer[name] . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
	if (!$mysqli->query("INSERT INTO timers (id, name, scene, tstart, startd, endd, days, months, skip) VALUES ('" 
							. $timer[id]. "','" 
							. $timer[name]. "','"
							. $timer[scene]. "','"
							. $timer[tstart]. "','"
							. $timer[startd]. "','"
							. $timer[endd]. "','"
							. $timer[days]. "','"
							. $timer[months]. "','"
							. $timer[skip]. "')"
							) 
			)
	{
		$apperr .= "mysqli_query INSERT error(" . $mysqli->errno . ") " . $mysqli->error . "\n" ;
		mysqli_close($mysqli);
		return (-1);
	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	$appmsg .= "add_timer successful\n" ;
	return(12);
}

//	-----------------------------------------------------------------------------------
//	Store the scene object in the database
//	
//	-----------------------------------------------------------------------------------
function store_timer($timer)
{	
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
//
	$test = "UPDATE timers SET name='{$timer[name]}', scene='{$timer[scene]}', tstart='{$timer[tstart]}', startd='{$timer[startd]}', endd='{$timer[endd]}', days='{$timer[days]}', months='{$timer[months]}', skip='{$timer[skip]}' WHERE id='$timer[id]' ";
	$apperr .= $test;
	if (!mysqli_query($mysqli,"UPDATE timers SET name='{$timer[name]}', scene='{$timer[scene]}', tstart='{$timer[tstart]}', startd='{$timer[startd]}', endd='{$timer[endd]}', days='{$timer[days]}', months='{$timer[months]}', skip='{$timer[skip]}' WHERE  id='$timer[id]' " ))
	{
		$apperr .= "Error: Store timer, ";
		$apperr .= "mysqli_query error" ;
	//		apperr .= "mysqli_query Error: " . mysqli_error($mysqli) ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "store_timer successful\n" ;
	return(13);
}

//-----------------------------------------------------------------------------------
//	Delete a timer record from the database. This is one of the element functions
//	needed to synchronize the database with the memory storage in the client, and
//	prevents information loss between reloads of the screen.
//	XXX Maybe we shoudl work with addr+unit+val instead of id+unit+val
//	-----------------------------------------------------------------------------------
function delete_timer($timer)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "timer id: ".$timer['id']." name: ".$timer['name']."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (".$mysqli->connect_errno.") ".$mysqli->connect_error,1);
	}
	if (!mysqli_query($mysqli, "DELETE FROM timers WHERE id='$timer[id]' " ))
	{
		$apperr .= "delete_timer:: mysqli_query error for timer: ".$timer['name'] ;
		return(-1);
	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "delete_timer successful\n" ;
	return(11);
}


// ----------------------------------------------------------------------------------
//
// Add a handset object as received from the ajax call and update mySQL
//
// -----------------------------------------------------------------------------------
function add_handset($handset)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr = "handset id: ".$handset[id]." name: ".$handset[name].", addr".$handset[addr]."\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
	
	if (!$mysqli->query("INSERT INTO handsets (id, name, brand, addr, unit, val, type, scene) VALUES ('" 
							. $handset[id] . "','" 
							. $handset[name]. "','"
							. $handset[brand]. "','"
							. $handset[addr]. "','"
							. $handset[unit]. "','"
							. $handset[val]. "','"
							. $handset[type]. "','"
							. $handset[seq]. "')"
							) 
			)
	{
		$apperr .= "mysqli_query INSERT error(" . $mysqli->errno . ") " . $mysqli->error . "\n" ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "add_handset successful\n" ;
	return(17);
}


// -----------------------------------------------------------------------------------
//	Store the handset record in the MySQL database
//	
//	-----------------------------------------------------------------------------------
function store_handset($handset)
{	
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") ".$mysqli->connect_error , 1);
		return (-1);
	}
//
	$test = "UPDATE handsets SET brand='{$handset[brand]}', addr='{$handset[addr]}',  name='{$handset[name]}', type='{$handset[type]}', scene='{$handset[scene]}' WHERE id='$handset[id]' AND unit='$handset[unit]' AND val='$handset[val]' ";
	$apperr = $test;
	
	if (!mysqli_query($mysqli,"UPDATE handsets SET brand='{$handset[brand]}', addr='{$handset[addr]}',  name='{$handset[name]}', type='{$handset[type]}', scene='{$handset[scene]}' WHERE id='$handset[id]' AND unit='$handset[unit]' AND val='$handset[val]' " ))
	{
		$apperr .= "Error: Store handset, ";
		$apperr .= "mysqli_query error" ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "store_handset successful\n" ;
	return(15);
}


/*	-----------------------------------------------------------------------------------
	Delete a handset record from the database. This is one of the element functions
	needed to synchronize the database with the memory storage in the client, and
	prevents information loss between reloads of the screen.
	
	-----------------------------------------------------------------------------------	*/
function delete_handset($handset)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "handset id: " . $handset[id] . " name: " . $handset[name]  . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
	}
	
	$msg = "DELETE FROM handsets WHERE id='$handset[id]' AND unit='$handset[unit]' AND val='$handset[val]' ";
	$apperr .= $msg;
	if (!mysqli_query($mysqli, "DELETE FROM handsets WHERE id='$handset[id]' AND unit='$handset[unit]' AND val='$handset[val]' " ))
	{
		$apperr .= "mysqli_query error" ;

	}
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "delete_handset successful\n" ;
	return(16);
}



/*	-----------------------------------------------------------------------------------
	Store the setting object in the database
	
	-----------------------------------------------------------------------------------	*/
function store_setting($setting)
{
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	// We need to connect to the database for start
	$apperr .= "Setting id: ".$setting[id] ." name: ".$setting[name] ." val: " . $setting[val] . "\n";
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return (-1);
	}
	
	$test = "UPDATE settings SET val='{$setting[val]}' WHERE id='$setting[id]' ";
	$apperr .= $test;
	if (!mysqli_query($mysqli,"UPDATE settings SET val='{$setting[val]}' WHERE  id='$setting[id]' " ))
	{
		$apperr .= "mysqli_query error" ;
//		apperr .= "mysqli_query Error: " . mysqli_error($mysqli) ;
		mysqli_close($mysqli);
		return (-1);
	}
	
//	mysqli_free_result($result);
	mysqli_close($mysqli);
	
	$appmsg .= "store_setting successful\n" ;
	return(5);
}


/* ---------------------------------------------------------------------------------
* function parse_controller_cmd(cmd);
*
* XXX We may have to update the devices object every now and then,
* in order to sync with the front-end...
*/
function parse_controller_cmd($cmd_str)
{	global $log;
	global $debug;
	global $devices;
	global $brands;
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON =>  
	$brand="-1";
	// Decode the room, the device and the value from the string
	list( $room, $dev, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$dev = "D".$dev;												// All devices recorded have D1--D16 as id
	for ($i=0; $i<count($devices); $i++) {
		//$log->lwrite("parse_controller_cmd:: room: ".$devices[$i]['room'].", dev: ".$devices[$i]['id'].", brand: ".$devices[$i]['brand']);
		if ( ($devices[$i]['room']==$room) && ($devices[$i]['id']==$dev)) {
			$bno = $devices[$i]['brand'];
			$brand=$brands[$bno]['fname'];				// XXX NOTE: The index in brand array IS the id no!!!
			if ($debug>0) $log->lwrite("parse_controller_cmd:: room: ".$room.", dev: ".$dev.", brand: ".$brand);
			break;
		}
	}
	return($brand);
}




// **************** SOCKET AND DAEMON FUNCTIONS *********************


/*	--------------------------------------------------------------------------------------------------	
* function send_2_daemon. (... send it to the daemon to sort it out ....)
* 
* Send a command to the LamPI daemon process for execution. For the moment we'll stick to the 
* protocol that is used by the ICS-1000 controller, but we might over time change to json format.
*
* Return values: -1 if fail, transaction number if success
*
* Since version 1.4 we use json, so for the LamPI daemon the message format has changed somewhat.
*
* In the first release of daemon command, it is used to send scene commands to the daemon,
* that will be parsed and then the individual commands in the scene will be inserted in the run 
* queue.
*
* XXX timer commands will be handled by the daemon itself as it parses the timer database
* about every minute for changed or new scene items.
* So the Queue is used for scenes (run now, or a time from now and for timers (run on some
* (or several) moment (agenda) in the future
* -----------------------------------------------------------------------------------------------------	*/
function send_2_daemon($cmd)
{
// Initializations
	global $apperr;
	global $appmsg;
	global $rcv_daemon_port;
	global $snd_daemon_port;
	global $log;
	
    $rport = $rcv_daemon_port;								// Remote Port for the client side, to recv port server side
	
    $_SESSION['tcnt']++;
    if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }		// Overflow

	// We will use json for encoding the messages to the daemon 
	// Message looks as follows:
	$snd = array(
    	'tcnt' => $_SESSION['tcnt'],
    	'action' => 'gui',
		'type' => 'raw',
		'message'=> $cmd
    );
	$cmd_pkg = json_encode($snd);
	
    if ($debug>=2) $apperr .= "daemon_cmd:: cmd_pkg: $cmd_pkg";
    
    $ok='';
    $from='';
    $buf="1";
    $rec=0;

// Initiate Networking

    // socket_set_option($ssock, SOL_SOCKET, SO_BROADCAST, 1); 
	
    $rsock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!socket_set_option($rsock, SOL_SOCKET, SO_REUSEADDR, 1)) // Re-use the port, do not block
    { 
		$apperr .= socket_strerror(socket_last_error($rsock)); 
		return(-1); 
    }

	// Get IP for this host ... As this is the webserver, we can find out which address to use
	//$ip = $_SERVER['SERVER_ADDR'];
	//$ip = '192.168.2.51';
	$ip = '127.0.0.1';
	if (socket_connect($rsock, $ip, $rport) === false)			
    {
       socket_close($rsock);
       $apperr .= 'socket_connect to '.$ip.':'.$rport.' failed: '.socket_strerror(socket_last_error())."\n";
	   return (-1);
    }
	
    $apperr = "socket_connect to address:port ".$ip.":".$rport." success<br>";	

    if (false === socket_write($rsock, $cmd_pkg, strlen($cmd_pkg)))
    {
      $apperr .= 'socket_send address - failed: ' ."<br>";
      socket_close($rsock);
      return(-1);
    }
    // $apperr = "socket_send address - success<br>";

	if(!socket_set_block($rsock))
    {
      socket_close($rsock);
      $apperr .= "socket_set_block: Error<br>";
	  return(-1);
    }							
	
	$timeo = array('sec' => 3, 'usec' => 0);
	if ( ! socket_set_option($rsock,SOL_SOCKET,SO_RCVTIMEO, $timeo)) {
		$apperr .= "Cannot set socket option TIMEO on receive socket<br>";
		return(-1);
	}
	
	// We may receive an answer immediately (just an ack) or we can timeout in 2 secs or so
    //if (!socket_recvfrom($rsock, $buf, 1024, MSG_WAITALL, $from, $rport))
	$len = socket_recv($rsock, $buf, 1024, 0);
	if (false === $len)
    {
      $err = socket_last_error($rsock);
      $apperr .= 'socket_recv failed with error '.$err.': '.socket_strerror($err) . "<br>";
	  socket_close($rsock);
	  return(-1);
    };
	$apperr .= "bytes read: ".$len;
	//$apperr .= ", buf: <".$buf.">";

// Need to check if the confirmation from the server matches the transaction id

	if (null == ($rcv = json_decode($buf, true) )) {
		$apperr .= " NULL ";
	}
	$ok = $rcv['message'];
	$tn = $rcv['tcnt'];

	$apperr .= "message <".$rcv['message'].">";
    //$apperr .= "Sent <$cmd_pkg> , rcvd <".$buf.">, transaction ".$tn." is ".$ok;
    
	if (socket_shutdown($rsock) == false) {
			$apperr .= "send_2_daemon:: socket shutdown failed";
	}
    socket_close($rsock);

	if (strcmp($ok,"OK") == 0) {
		return($tn); 
	} 
	else {
		$apperr .= "Nah";
		return(-1); 
	}
}


?>