<?php 
require_once( './backend_cfg.php' );

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
// follows (backend_set.php)
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
	$weatheron = array();
	
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

	$sqlCommand = "SELECT id, name, scene, tstart, startd, endd, days, months FROM timers";
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
	
	$sqlCommand = "SELECT id, name, location, brand, address, channel, temperature, humidity, windspeed, winddirection rainfall FROM weather";
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
	$config ['weatheron']= $weather;
	
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


/* --------------------------------------------------------------------------------
* Function read scene from MySQL
*
* Lookup the scene with the corresponding name
* ----------------------------------------------------------------------------------- */
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


/*	-----------------------------------------------------------------------------------
*	Store the scene record in the MySQL database
*	
*	-----------------------------------------------------------------------------------	*/
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


/*	-----------------------------------------------------------------------------------
	Delete a scene record from the database. This is one of the element functions
	needed to synchronize the database with the memory storage in the client, and
	prevents information loss between reloads of the screen.
	
	-----------------------------------------------------------------------------------	*/
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
	if (!$mysqli->query("INSERT INTO timers (id, name, scene, tstart, startd, endd, days, months) VALUES ('" 
							. $timer[id]. "','" 
							. $timer[name]. "','"
							. $timer[scene]. "','"
							. $timer[tstart]. "','"
							. $timer[startd]. "','"
							. $timer[endd]. "','"
							. $timer[days]. "','"
							. $timer[months]. "')"
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

/*	-----------------------------------------------------------------------------------
	Store the scene object in the database
	
	-----------------------------------------------------------------------------------	*/
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
	$test = "UPDATE timers SET name='{$timer[name]}', scene='{$timer[scene]}', tstart='{$timer[tstart]}', startd='{$timer[startd]}', endd='{$timer[endd]}', days='{$timer[days]}', months='{$timer[months]}' WHERE id='$timer[id]' ";
	$apperr .= $test;
	if (!mysqli_query($mysqli,"UPDATE timers SET name='{$timer[name]}', scene='{$timer[scene]}', tstart='{$timer[tstart]}', startd='{$timer[startd]}', endd='{$timer[endd]}', days='{$timer[days]}', months='{$timer[months]}' WHERE  id='$timer[id]' " ))
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

/*	-----------------------------------------------------------------------------------
	Delete a timer record from the database. This is one of the element functions
	needed to synchronize the database with the memory storage in the client, and
	prevents information loss between reloads of the screen.
	XXX Maybe we shoudl work with addr+unit+val instead of id+unit+val
	-----------------------------------------------------------------------------------	*/
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
// ----------------------------------------------------------------------------------- */
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


/*	-----------------------------------------------------------------------------------
*	Store the handset record in the MySQL database
*	
*	-----------------------------------------------------------------------------------	*/
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


/*	=======================================================================================	
	Function load_weatherdb database
	
	=======================================================================================	*/
function load_weatherdb()
{
	// We assume that a database has been created by the user. host/name/passwd in backend_cfg.php
	global $dbname, $dbuser, $dbpass, $dbhost;	
	global $appmsg, $apperr;
	
	$weatherdb = array();
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		decho("Failed to connect to MySQL on host ".$dbhost." (" . $mysqli->connect_errno . ") " . $mysqli->connect_error , 1);
		return(-1);
	}
	
	$sqlCommand = "SELECT id, timestamp, brand, location, brand, address, channel, temperature, humidity, windspeed, winddirection, rainfall FROM weatherdb";
	$query = mysqli_query($mysqli, $sqlCommand) or die (mysqli_error());
	while ($row = mysqli_fetch_assoc($query)) { 
		$weatherdb[] = $row ;
	}
	mysqli_free_result($query);	
	mysqli_close($mysqli);
	$apperr = "";										// No error
	return ($weatherdb);
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
	
    $rport = $rcv_daemon_port;								// Send Port for the client side, to recv port server side
	
    $_SESSION['tcnt']++;
    if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }		// Overflow

	// We will use json for encoding the messages to the daemon 
	// Message looks as follows:
	//$snd = array(
    //	'tcnt' => $_SESSION['tcnt'],
    //	'status' => 'OK',
	//	'cmd'=> $cmd,
	//	'data'=>$data,
    //);
	//$cmd_pkg = json_encode($snd);
	
    //$cmd_pkg = sprintf('%03d,%s\r\n', $_SESSION['tcnt'] , $cmd); 
	$cmd_pkg = sprintf('%03d,%s', $_SESSION['tcnt'] , $cmd);
	
    if ($debug>1) $apperr .= "daemon_cmd:: cmd_pkg: $cmd_pkg";
    
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
	$ip = '192.168.2.51';
	//$ip = '127.0.0.1';
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
    $apperr = "socket_send address - success<br>";

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
	
	// We may receive an anwer immediately (just an ack) or we can timeout in 2 secs or so
    //if (!socket_recvfrom($rsock, $buf, 1024, MSG_WAITALL, $from, $rport))
	$len = socket_recv($rsock, $buf, 1024, 0);
	if (false === $len)
    {
      $err = socket_last_error($rsock);
      $apperr .= 'socket_recv failed with error '.$err.': '.socket_strerror($err) . "<br>";
	  socket_close($rsock);
	  return(-1);
    };

// Need to check if the confirmation from the server matches the transaction id

    $apperr .= "Rcvd ".$buf." from ".$ip.":".$rport."<br>";
	
    $len=strlen($buf);
    $i = strpos($buf,',');
	$tn = (int)substr($buf,0,$i); 
	$ok = substr($buf,$i+1,2);

    $apperr .= "Sent <$cmd_pkg> , rcvd <".$buf.">, len=".$len." transaction ".$tn." is ".$ok;
    
	if (socket_shutdown($rsock) == false) {
			$apperr .= "send_2_daemon:: socket shutdown failed";
	}
    socket_close($rsock);

	if (strcmp($ok,"OK")==0) {
		return($tn); 
	} 
	else {
		$apperr .= $buf;
		return(-1); 
	}
}



// ******************** DEVICE HANDLING FUNCTIONS ********************************
//
// XXX NOTE: Device functions are handled by LamPI-receiver program from now ...
// Functions below become obsolete
//
// Although I have written some wiringPI code myself, I must rely on shell programs
// written by others as well (see the sources in ~/src/xxxxx for more detail.
//
//	kaku_cmd
//	action_cmd:		IMPULS switches bought from action in the Netherlands
//	old_kaku_cmd
//	blokker_cmd		XXX not tested, I do not have these myself, so may have to adapt
//
// **********************************************************************************


/* ---------------------------------------------------------------------------------- 
* Function kaku_cmd
*
* Handles a specific Kaku command like !R3D10F1 which means Room 3, Device 10, Lamp on
* The sytax of the commands is equal to that which is sent to the iCS_1000 controller
* for compatibility reasons.
* Should we not already have a ICS-1000, then json would have been a better option!
*/
function kaku_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./kaku -g 100 -n 2 on 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow
	
	// Make a translation table
	$ttable = array(
		'1'  => '100', 
		'2'  => '101',
		'3'  => '102',
		'4'  => '103',
		'5'  => '104',
		'6'  => '105',
		'7'  => '106',
		'8'  => '107',
		'9'  => '108',
		'10' => '109',
		'11' => '110',
		'12' => '111',
		'13' => '112',
		'14' => '113',
		'15' => '114',
		'16' => '115'		
		);
	
	// Decode the room, the device and the value from the string
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result  = " -p ".$wiringPi_snd;
	$result .= " -g ".$ttable[$room];
	$result .= " -n ".$device." " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = ceil( (substr($value, 2) /2)) -1;				// Command line interface accepts 4 bits !!
		$result .= $value;
	} else 
	// in face F1
	if ($value == '1' ) {
		$result .= "on";
	} 
	else if ($value == 'o') {
		// Fo, use last dimmer value
		// XXX not yet, and not needed at the moment
		$result .= "on";
	}
	else {
		// F0, switch off
		$result .= "off";
	}
	
	$apperr = "kaku_cmd cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";
	//store_device(); // QQQ 
	
	// sudo ./kaku string ...... Recompiled kaku to echo "OK" when returning
	// successfully. Necessary, if shell_exec executes command without output
	// then return value will be NULL (for some strange reason)
	$exec_str = 'cd /home/pi/exe; ./kaku '.$result ;
	// $log->lwrite("kakucmd:: ".$exec_str);
	
	if (shell_exec($exec_str . " 2>&1 && echo ' '")  === NULL ) {
		$apperr .= "\nERROR: kaku " . $result . "\n ";
		return (-1);
	}
	
	return($_SESSION['tcnt']);
}

/* -----------------------------------------------------------------------------------------------
* Function old_kaku_cmd
*
* XXX Handles calls to old kaku equipment.
*/
function old_kaku_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./kaku A 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow
	
	// Make a translation table
	$ttable = array(
		'1'  => 'A', 
		'2'  => 'B',
		'3'  => 'C',
		'4'  => 'D',
		'5'  => 'E',
		'6'  => 'F',
		'7'  => 'G',
		'8'  => 'H',
		'9'  => 'I',
		'10' => 'J',
		'11' => 'K',
		'12' => 'L',
		'13' => 'M',
		'14' => 'N',
		'15' => 'O',
		'16' => 'P'		
		) ;
	
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result = $ttable[$room] . " " . $device . " " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = substr($value, 2);
		$result .= $value;
	} else 
	// in case F1
	if ($value == '1' ) {
		$result .= "on";
	} else {
	// mus be F0
		$result .= "off";
	}
	
	$apperr = "\nold_kaku:: cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";	
	$appmsg .= ", result rasp_cmd: " . $result . ".";	
	
	// sudo ./kaku string ...... then webuser must be in sudoers
	$exec_str = 'cd /home/pi/exe; ./kakuold '. $result .'' ;
	
	if (shell_exec($exec_str . " 2>&1")  === NULL ) {
		$apperr .= "\nERROR: Shell_exec: " . $exec_str . "\n ";
		return (-1);
	}
	return($_SESSION['tcnt']);
}

/* -----------------------------------------------------------------------------------------------
* FUNCTION ACTION_CMD
*
* Handles calls to Action/Impuls equipment.
* 
*/
function action_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./action 1 B on 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow

	// Make a translation table
	$ttable = array(
		'1'  => 'A', 
		'2'  => 'B',
		'3'  => 'C',
		'4'  => 'D',
		'5'  => 'E',
		'6'  => 'F',
		'7'  => 'G',
		'8'  => 'H',
		'9'  => 'I',
		'10' => 'J',
		'11' => 'K',
		'12' => 'L',
		'13' => 'M',
		'14' => 'N',
		'15' => 'O',
		'16' => 'P'		
		) ;
	
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result  = " -p ".$wiringPi_snd;
	$result .= " -g ".$room;
	$result .= " -n ".$ttable[$device]." " ;
	//$result = $room . " " . $ttable[$device] . " " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = substr($value, 2);
		$result .= $value;
	} else 
	// in case F1
	if ($value == '1' ) {
		$result .= "on";
	} else {
	// F0
		$result .= "off";
	}
	
	//$apperr .= "\nparse cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";	
	$appmsg .= ", result rasp_cmd: ".$result.".";	
	
	// sudo ./action string ...... 
	$exec_str = 'cd /home/pi/exe; ./action '.$result.'' ;
	$log->lwrite("action_cmd:: ".$exec_str);
	if (shell_exec($exec_str . " 2>&1 && echo ' '") === NULL ) {
		$apperr .= "\nERROR: Shell_exec: ".$exec_str."\n ";
		return (-1);
	}
	return($_SESSION['tcnt']);
}

/* -----------------------------------------------------------------------------------------------
* FUNCTION ELRO_CMD
*
* Handles calls to Elro equipment.
* 
*/
function elro_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./kaku A 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow

	// Make a translation table
	$ttable = array(
		'1'  => 'A', 
		'2'  => 'B',
		'3'  => 'C',
		'4'  => 'D',
		'5'  => 'E',
		'6'  => 'F',
		'7'  => 'G',
		'8'  => 'H',
		'9'  => 'I',
		'10' => 'J',
		'11' => 'K',
		'12' => 'L',
		'13' => 'M',
		'14' => 'N',
		'15' => 'O',
		'16' => 'P'		
		) ;
	
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result = $room . " " . $ttable[$device] . " " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = substr($value, 2);
		$result .= $value;
	} else 
	// in face F1
	if ($value == '1' ) {
		$result .= "on";
	} else {
		// F0
		$result .= "off";
	}
	
	//$apperr .= "\nparse cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";	
	$appmsg .= ", result rasp_cmd: " . $result . ".";	
	
	// sudo ./action string ...... 
	//
	$exec_str = 'cd /home/pi/exe; ./elro '. $result .'' ;
	if (shell_exec($exec_str . " 2>&1 && echo ' '") === NULL ) {
		$apperr .= "\nERROR: Shell_exec: " . $exec_str . "\n ";
		return (-1);
	}
	return($_SESSION['tcnt']);
}

/* -----------------------------------------------------------------------------------------------
* FUNCTION BLOKKER_CMD
*
* Handles calls to Blokker equipment.
* 
*/
function blokker_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./kaku A 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow

	// Make a translation table
	$ttable = array(
		'1'  => 'A', 
		'2'  => 'B',
		'3'  => 'C',
		'4'  => 'D',
		'5'  => 'E',
		'6'  => 'F',
		'7'  => 'G',
		'8'  => 'H',
		'9'  => 'I',
		'10' => 'J',
		'11' => 'K',
		'12' => 'L',
		'13' => 'M',
		'14' => 'N',
		'15' => 'O',
		'16' => 'P'		
		) ;
	
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result = $room . " " . $ttable[$device] . " " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = substr($value, 2);
		$result .= $value;
	} else 
	// in face F1
	if ($value == '1' ) {
		$result .= "on";
	} else {
		// F0
		$result .= "off";
	}
	
	//$apperr .= "\nparse cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";	
	$appmsg .= ", result rasp_cmd: " . $result . ".";	
	
	// sudo ./action string ...... 
	//
	$exec_str = 'cd /home/pi/exe; ./blokker '. $result .'' ;
	if (shell_exec($exec_str . " 2>&1 && echo ' '") === NULL ) {
		$apperr .= "\nERROR: Shell_exec: " . $exec_str . "\n ";
		return (-1);
	}
	return($_SESSION['tcnt']);
}

/* ---------------------------------------------------------------------------------- 
* Function livolo_cmd
*
* Handles a specific Kaku command like !R3D10F1 which means Room 3, Device 10, Lamp on
* The sytax of the commands is equal to that which is sent to the iCS_1000 controller
* for compatibility reasons.
* Should we not already have a ICS-1000, then json would have been a better option!
*/
function livolo_cmd($cmd_str)
{
	// Initializations
	global $apperr;
	global $appmsg;
	global $wiringPi_snd;					// pin number set in backend_cfg.php file
	global $log;
	
	// Parse the cmd_str on the ICS way and translate to Raspberry commands
	// !R1D2F1 Room 1, Device 2, ON => ./kaku -g 100 -n 2 on 
	
	$_SESSION['tcnt']++;
	if ($_SESSION['tcnt']==999) { $_SESSION['tcnt'] = 1; }	// Overflow
	
	// Make a translation table
	$ttable = array(
		'1' => '23783', 
		'2' => '23783'		
		);
	
	// Decode the room, the device and the value from the string
	list( $room, $device, $value ) = sscanf ($cmd_str, "!R%dD%dF%s" );
	$result  = " -p ".$wiringPi_snd;
	$result .= " -g ".$ttable['1'];								// XXX This is not correct, need to extract the addr
	$result .= " -n ".$device." " ;
	
	// In case it is a dim command
	if (substr($value, 0, 2 ) == "dP" ) {
		$value = ceil( (substr($value, 2) /2)) -1;				// Command line interface accepts 4 bits !!
		$result .= $value;
	} else 
	// in face F1
	if ($value == '1' ) {
		$result .= "on";
	} 
	else if ($value == 'o') {
		// Fo, use last dimmer value
		// XXX not yet, and not needed at the moment
		$result .= "on";
	}
	else {
		// F0, switch off
		$result .= "off";
	}
	
	$apperr = "livolo_cmd cmd_str: ".$cmd_str." , room: ".$room." device: ".$device." value: ".$value."\n";
	//store_device(); // QQQ 
	
	// sudo ./kaku string ...... Recompiled kaku to echo "OK" when returning
	// successfully. Necessary, if shell_exec executes command without output
	// then return value will be NULL (for some strange reason)
	$exec_str = 'cd /home/pi/exe; ./livolo '.$result ;
	
	if (shell_exec($exec_str . " 2>&1 && echo ' '")  === NULL ) {
		$apperr .= "\nERROR: livolo " . $result . "\n ";
		return (-1);
	}
	
	return($_SESSION['tcnt']);
}


?>