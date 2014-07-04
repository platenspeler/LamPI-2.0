<?php 
require_once( '../daemon/backend_cfg.php' );
// require_once( '../daemon/backend_lib.php' );

/*	------------------------------------------------------------------------------	
	Note: Program to switch klikaanklikuit and coco equipment
	Author: Maarten Westenberg
	Version 1.0 : August 16, 2013
	Version 1.2 : August 30, 2013 removed all init function from file into a separate file
	Version 1.3 : September 6, 2013 Implementing first version of Daemon process
	Version 1.4 : Sep 20, 2013
	Version 1.5 : Oct 20, 2013
	Version 1.6 : Nov 10, 2013
	Version 1.7 : Dec 2013
	Version 1.8 : Jan 18, 2014
	Version 1.9 : Mar 10, 2014
	Version 2.0 : Jun 15, 2014
	
	-------------------------------------------------------------------------------	*/


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

// ---------------------------------------------------------------------------------
//
//	Function load_weatherdb database
//	
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



// -----------------------------------------------------------------------------------
//	Store the setting object in the database
//	
//	-----------------------------------------------------------------------------------	
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


// -------------------------------------------------------------------------------
// DBASE_PARSE(
//
function dbase_parse($cmd,$message)
{
	//
	switch($cmd)
	{
		case "add_device":
			$ret= add_device($message);
		break;
		case "delete_device":
			$ret= delete_device($message);
		break;
		case "add_room":
			$ret= add_room($message);
		break;
		case "delete_room":
			$ret= delete_room($message);
		break;
		case "add_scene":
			$ret= add_scene($message);
		break;
		case "delete_scene":
			$ret= delete_scene($message);
		break;
		case "upd_scene":
			$ret= upd_scene($message);
		break;
		case "add_timer":
			$ret= add_timer($message);
		break;
		case "delete_timer":
			$ret= delete_timer($message);
		break;
		case "store_timer":
			$ret= store_timer($message);
		break;
		case "add_handset":
			$ret= add_handset($message);
		break;
		case "delete_handset":
			$ret= delete_handset($message);
		break;
		case "store_handset":
			$ret= store_handset($message);
		break;
		case "add_weather":
			$ret= add_weather($message);
		break;
		case "delete_weather":
			$ret= delete_weather($message);
		break;
		case "store_setting":
			$ret= store_setting($message);
		break;
		
		default:
	}
	if ($ret >= 0) {									// Prepare structure to send back to the calling ajax client (in stdout)
		$send = array(
    		'tcnt' => $ret,
			'appmsg'=> $appmsg,
    		'status' => 'OK',
			'apperr'=> $apperr,
    	);
		$output=json_encode($send);
	}
	else {												//	Functions need to fill apperr themselves!	
		$send = array(
    		'tcnt' => $ret,
			'appmsg'=> $appmsg,
    		'status' => 'ERR',
			'apperr' => $apperr,
		);
		$output=json_encode($send);
	}
	return $output;
}

?>