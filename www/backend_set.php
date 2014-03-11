<?php 
require_once ( './backend_cfg.php' );
require_once ( './backend_lib.php' );

/*	======================================================================================	
	Note: Program to switch klikaanklikuit and coco equipment
	Author: Maarten Westenberg
	Version 1.0 : August 16, 2013
	Version 1.3 : August 30, 2013
	Version 1.4 : 
	Version 1.5 : Oct 20, 2013
	Version 1.6 : Nov 10, 2013
	Version 1.7 : Dec 2013
	Version 1.8 : Jan 17, 2014

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
error_reporting(E_ALL);
header("Cache-Control: no-cache");
header("Content-type: application/json");					// Input and Output are XML coded

if (!isset($_SESSION['debug']))	{ $_SESSION['debug']=0; }
if (!isset($_SESSION['tcnt']))	{ $_SESSION['tcnt'] =0; }

$apperr = "";	// Global Error. Just append something and it will be sent back
$appmsg = "";	// Application Message (from backend to Client)


/*	======================================================================================	
	Function write complete database to file
	
	======================================================================================	*/
function file_database($fname, $cfg)
{
	$ret = file_put_contents ( $fname, json_encode($cfg, JSON_PRETTY_PRINT));
	if ( $ret === false )
	{
		$apperr .= "\nError file_database\n";
		$ret = -1;
	}
	else {
		$appmsg .= "\nSuccess file_database\n";
	}
	return ($ret);
}

/*	======================================================================================	
	Function read complete database from file
	
	======================================================================================	*/
function read_database($fname)
{

	$ret = file_get_contents ( $fname );
	if ( $ret === false )
	{
		$apperr .= "\nError read_database from file: ".$fname."\n";
		$ret = -1;
		echo "ERROR read database";
		return(-1);
	}
	else {
		$appmsg .= "\nSuccess read_database\n";
	}
	$cfg = json_decode($ret, true);
	return ($cfg);
}


/*	======================================================================================	
	Function to create a database, and fill it with values as specified in its parameters
	For testing purposes, we want to be able to reset the database,
	and reread some initial values that make sense in my (your)
	home.
	
	The array below contains such an initial database, after
	reading it, we want to populate the MySQL database with these values
	======================================================================================	*/
function fill_database($cfg)
{
	$rooms = $cfg['rooms'];
	$devices = $cfg['devices'];
	$scenes = $cfg['scenes'];
	$timers = $cfg['timers'];
	$settings = $cfg['settings'];
	$controllers = $cfg['controllers'];
	$handsets = $cfg['handsets'];
	$brands = $cfg['brands'];
	$weather = $cfg['weather'];
	
	 // We assume that a database has been created by the user
	global $dbname;
	global $dbuser;
	global $dbpass;
	global $dbhost;
	
	// We need a table rooms, devices, scenes and timers to start
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		echo "Failed to connect to MySQL: host ".$dbhost." (".$mysqli->connect_errno.") ".$mysqli->connect_error;
	}
	// Success,  so we can start filling the database
	
	// --------------------------------------------------------------------------
	// Create table rooms
	// Please note that drop command needs special permissions 
	
	if (!$mysqli->query("DROP TABLE IF EXISTS rooms") ||
    	!$mysqli->query("CREATE TABLE rooms(id INT, name CHAR(20) )") )
	{
    	echo "Table creation rooms failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	for ($i=0; $i < count($rooms); $i++)
	{
		if (!$mysqli->query("INSERT INTO rooms (id, name) VALUES ('" 
							. $rooms[$i][id]. "','" 
							. $rooms[$i][name]. "')"
							) 
			)
		{
			echo "Table Insert failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// ----------------------------------------------------------
	// create table devices
	if (!$mysqli->query("DROP TABLE IF EXISTS devices") ||
    	!$mysqli->query("CREATE TABLE devices(id CHAR(3), gaddr CHAR(12), room CHAR(12), name CHAR(20), type CHAR(12), val INT, lastval INT, brand CHAR(20) )") )
	{
    	echo "Table creation devices failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert devices	
	for ($i=0; $i < count($devices); $i++)
	{
		if (!$mysqli->query("INSERT INTO devices (id, gaddr, room, name, type, val, lastval, brand) VALUES ('" 
							. $devices[$i][id]. "','" 
							. $devices[$i][gaddr]. "','"
							. $devices[$i][room]. "','"
							. $devices[$i][name]. "','"
							. $devices[$i][type]. "','"
							. $devices[$i][val]. "','"
							. $devices[$i][lastval]. "','"
							. $devices[$i][brand]. "')"
							) 
			)
		{
			echo "Table Insert failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// ----------------------------------------------------------
	// Insert SCENES
	// Fr now we declare seq string 255 which is the max for mySQL. ICS specs mention 256(!) chars max 
	if (!$mysqli->query("DROP TABLE IF EXISTS scenes") ||
    	!$mysqli->query("CREATE TABLE scenes(id INT, val INT, name CHAR(20), seq CHAR(255) )") )
	{
    	echo "Table creation scenes failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Scenes
	for ($i=0; $i < count($scenes); $i++)
	{
		if (!$mysqli->query("INSERT INTO scenes (id, val, name, seq ) VALUES ('" 
							. $scenes[$i][id]. "','" 
							. $scenes[$i][val]. "','"
							. $scenes[$i][name]. "','"
							. $scenes[$i][seq]. "')"
							) 
			)
		{
			echo "Table Insert failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}

	// -------------------------------------------------------
	// Timers
	//  
	if (!$mysqli->query("DROP TABLE IF EXISTS timers") ||
    	!$mysqli->query("CREATE TABLE timers(id INT, name CHAR(20), scene CHAR(20), tstart CHAR(20), startd CHAR(20), endd CHAR(20), days CHAR(20), months CHAR(20) )") )
	{
    	echo "Table creation timers failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert Timers
	for ($i=0; $i < count($timers); $i++)
	{
		if (!$mysqli->query("INSERT INTO timers (id, name, scene, tstart, startd, endd, days, months ) VALUES ('" 
							. $timers[$i][id]. "','" 
							. $timers[$i][name]. "','"
							. $timers[$i][scene]. "','"
							. $timers[$i][tstart]. "','"
							. $timers[$i][startd]. "','"
							. $timers[$i][endd]. "','"
							. $timers[$i][days]. "','"
							. $timers[$i][months]. "')"
							) 
			)
		{
			echo "Table Insert Timers failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// --------------------------------------------------------------------
	// Handsets
	//  
	if (!$mysqli->query("DROP TABLE IF EXISTS handsets") ||
    	!$mysqli->query("CREATE TABLE handsets(id INT, name CHAR(20), brand CHAR(20), addr CHAR(20), unit INT, val INT, type CHAR(20), scene CHAR(255) )") )
	{
    	echo "Table creation handsets failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert Handsets
	for ($i=0; $i < count($handsets); $i++)
	{
		if (!$mysqli->query("INSERT INTO handsets (id, name, brand, addr, unit, val, type, scene ) VALUES ('" 
							. $handsets[$i][id]. "','" 
							. $handsets[$i][name]. "','"
							. $handsets[$i][brand]. "','"
							. $handsets[$i][addr]. "','"
							. $handsets[$i][unit]. "','"
							. $handsets[$i][val]. "','"
							. $handsets[$i][type]. "','"
							. $handsets[$i][scene]. "')"
							) 
			)
		{
			echo "Table Insert handsets failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// -------------------------------------------------------------
	// Settings 
	if (!$mysqli->query("DROP TABLE IF EXISTS settings") ||
    	!$mysqli->query("CREATE TABLE settings(id INT, val CHAR(128), name CHAR(20) )") )
	{
    	echo "Table creation setting failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert Settings
	for ($i=0; $i < count($settings); $i++)
	{
		if (!$mysqli->query("INSERT INTO settings (id, val, name ) VALUES ('" 
							. $settings[$i][id]. "','" 
							. $settings[$i][val]. "','"
							. $settings[$i][name]. "')"
							) 
			)
		{
			echo "Table Insert settings failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// -------------------------------------------------------------
	// Controllers
	if (!$mysqli->query("DROP TABLE IF EXISTS controllers") ||
    	!$mysqli->query("CREATE TABLE controllers(id INT, name CHAR(20), fname CHAR(128) )") )
	{
    	echo "Table creation controllers failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert Controllers
	for ($i=0; $i < count($controllers); $i++)
	{
		if (!$mysqli->query("INSERT INTO controllers (id, name, fname ) VALUES ('" 
							. $controllers[$i][id]. "','" 
							. $controllers[$i][name]. "','" 
							. $controllers[$i][fname]. "')"
							) 
			)
		{
			echo "Table Insert controllers failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// --------------------------------------------------------------
	// Brands
	if (!$mysqli->query("DROP TABLE IF EXISTS brands") ||
    	!$mysqli->query("CREATE TABLE brands(id INT, name CHAR(20), fname CHAR(128) )") )
	{
    	echo "Table creation brands failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert Brands
	for ($i=0; $i < count($brands); $i++)
	{
		if (!$mysqli->query("INSERT INTO brands (id, name, fname ) VALUES ('" 
					. $brands[$i][id]. "','" 
					. $brands[$i][name]. "','" 
					. $brands[$i][fname]. "')"
					) 
			)
		{
			echo "Table Insert brands failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	// --------------------------------------------------------------
	// Weather
	if (!$mysqli->query("DROP TABLE IF EXISTS weather") ||
    	!$mysqli->query("CREATE TABLE weather(id INT, name CHAR(20), location CHAR(20), brand CHAR(20), address CHAR(20), channel CHAR(8), temperature CHAR(8), humidity CHAR(8), windspeed CHAR(8), winddirection CHAR(8), rainfall CHAR(8) )") )
	{
    	echo "Table creation weather failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	// Insert weather data
	for ($i=0; $i < count($weather); $i++)
	{
		if (!$mysqli->query("INSERT INTO weather (id, name, location, brand, address, channel, temperature, humidity, windspeed, winddirection, rainfall ) VALUES ('" 
					. $weather[$i][id]. "','" 
					. $weather[$i][name]. "','"
					. $weather[$i][location]. "','"
					. $weather[$i][brand]. "','"
					. $weather[$i][address]. "','"
					. $weather[$i][channel]. "','"
					. $weather[$i][temperature]. "','"
					. $weather[$i][humidity]. "','"
					. $weather[$i][windspeed]. "','"
					. $weather[$i][winddirection]. "','"
					. $weather[$i][rainfall]. "')"
					) 
			)
		{
			echo "Table Insert weather failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	
	return(1);
}


/*	=======================================================================================	
	Function to print a database, 
	
	=======================================================================================	*/
function print_database($cfg)
{
	echo " print database opened succesfully : \n" ;

	$rooms = $cfg["rooms"];
	$devices = $cfg["devices"];
	$scenes = $cfg["scenes"];
	$timers = $cfg["timers"];
	$handsets = $cfg["handsets"];
	$settings = $cfg["settings"];
	$controllers = $cfg["controllers"];
	$brands = $cfg["brands"];
	$weather = $cfg["weather"];

	var_dump(get_object_vars($cfg));
	echo " print database started succesfully";
	
	// Rooms
	echo(" Start printing the database\n");
	echo("Count of rooms: " . count($rooms) . "\n");
	for ($i=0; $i < count($rooms); $i++) {
		echo("index: $i, id: " . $rooms[$i][id] . ", data: " . $rooms[$i][name] . ", \n");
	}
	echo("\n");
	
	// Devices
	echo("Count of devices: " . count($devices) . "\n");
	for ($i=0; $i < count($devices); $i++) 	{
		echo("index: $i id: ".$devices[$i][id].", gaddr: ".$devices[$i][gaddr].", room: ".$devices[$i][room]);
		echo(", data: ".$devices[$i][name].", brand: ".$devices[$i][brand].", \n");
	}
	echo("\n");	
	
	// Scenes
	echo("Count of scenes: " . count($scenes) . "\n");
	for ($i=0; $i < count($scenes); $i++) {
		echo("index: $i id: ". $scenes[$i][id].", name: ".$scenes[$i][name].", seq: ".$scenes[$i][seq]. ", \n");
	}
	echo("\n");
	
	// Timers
	echo("Count of timers: ".count($timers)."\n");
	for ($i=0; $i < count($timers); $i++) {
		echo("index: $i id: ". $timers[$i][id].", name: ".$timers[$i][name]);
		echo(", scene: ". $timers[$i][scene].", startd: ".$timers[$i][startd].", endd: ".$timers[$i][endd]);
		echo(", tstart: ". $timers[$i][tstart].", days: ".$timers[$i][days].", months: ".$timers[$i][months]."\n");
	}
	echo("\n");
	
	// Handsets
	echo("Count of handsets: " . count($handsets) . "\n");
	for ($i=0; $i < count($handsets); $i++) {
		echo("index: $i id: ". $handsets[$i][id].", name: ".$handsets[$i][name].", brand: ".$handsets[$i][brand]);
		echo(", addr: ".$handsets[$i][addr].", seq: ".$handsets[$i][unit] );
		echo(", val: ".$handsets[$i][val].  ", type: ".$handsets[$i][type].", scene: ".$handsets[$i][scene]."\n");
	}
	echo("\n");
	
	// Settings
	echo("Count of settings: ".count($settings)."\n");
	for ($i=0; $i < count($settings); $i++) {
		echo("index: $i id: " . $settings[$i][id].", val: ".$settings[$i][val].", name: ".$settings[$i][name]."\n");
	}
	echo("\n");
	
	// Controllers
	echo("Count of controllers: ".count($controllers)."\n");
	for ($i=0; $i < count($controllers); $i++) {
		echo("index: $i id: ".$controllers[$i][id].", name: ".$controllers[$i][name].", fname: ".$controllers[$i][fname]."\n");
	}
	echo("\n");
	
	// Brands
	echo("Count of brands: " . count($brands) . "\n");
	for ($i=0; $i < count($brands); $i++) {
		echo("index: $i id: ".$brands[$i][id].", name: ".$brands[$i][name].", fname: ".$brands[$i][fname]."\n");
	}
	echo("\n");
	
	// weather
	echo("Count of weather: " . count($weather) . "\n");
	for ($i=0; $i < count($weather); $i++) {
		echo("index: $i id: ".$weather[$i][id]
				.", name: ".$weather[$i][name].", location: ".$weather[$i][location]
				.", brand: ".$weather[$i][brand].", address: ".$weather[$i][address]
				.", channel: ".$weather[$i][channel].", temperature: ".$weather[$i][temperature]
				.", humidity: ".$weather[$i][humidity].", windspeed: ".$weather[$i][windspeed]
				.", winddirection: ".$weather[$i][winddirection]
				.", rainfall: ".$weather[$i][rainfall]."\n");
	}
	echo("\n");
	
	echo("print_database:: Sucess\n\n");
}


/*	======================================================================================	
	Function read weather logfile from file
	
	======================================================================================	*/
function read_weatherdb($fname)
{
	
	echo "Reading weather from file: ".$fname."\n";
	$ret = file($fname,FILE_IGNORE_NEW_LINES);
	if ( $ret === false )
	{
		$apperr .= "\nError read_weatherdb from file: ".$fname."\n";
		$ret = -1;
		echo "ERROR read database\n";
		return(-1);
	}
	else {
		$appmsg .= "\nSuccess read_weatherdb\n";
	}
	
	for ($i=0; $i< count($ret); $i++)
	{
		$n = sscanf($ret[$i],"[%d/%3s/%d, %d:%d:%d] (LamPI-daemon) address: %d, channel: %d, temperature: %d.%d, humidity: %d" ,
								$day, $mon, $yr, $hr, $min, $sec,
								$address, $channel, $temp1, $temp2, $humidity );
		// $msqldate = date( 'Y-m-d H:i:s', $phpdate );
		
		$weathr= array (
			// These should be all values for a weather station, not all will
			// be present, depending on the version, number of sensors etc.
			// I you like to add more field, make sure you do with the MSQL function below as well.
			'id' => $i,
			'timestamp' => $yr."-".$mon."-".$day." ".$hr.":".$min.":".$sec ,
			'name' => "",
			'location'  => "",					
			'brand'  => "",
			'address'  => $address,
			'channel'  => $channel,
			'temperature'  => $temp1.".".$temp2,
			'humidity'  => $humidity,
			'windspeed'  => "",
			'winddirection' => "",
			'rainfall' => ""
		);
		$weatherdb[] = $weathr;
		//echo "Record: ".$i.", timestamp: ".$weathr['timestamp'].", address: ".$weathr['address']
		//	.", channel: ".$weathr['channel'].", Temp: ".$weathr['temperature']
		//	.", Humid: ".$weathr['humidity']."\n";
	}
	
	return ($weatherdb);
}


/*	=======================================================================================	
	Function to create tables for the LamPI-weather functions
	The database will contain weather station data such as Address, Channel, 
	temperature, humidity, windspeed and winddirection
	
	=======================================================================================	*/ 
function fill_weatherdb($weatherdb)
{
	// We assume that a database has been created by the user
	global $dbname;
	global $dbuser;
	global $dbpass;
	global $dbhost;
	
	// We need a table to start
	
	$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
	if ($mysqli->connect_errno) {
		echo "Failed to connect to MySQL: host ".$dbhost." (".$mysqli->connect_errno.") ".$mysqli->connect_error;
	}
	
	// Success,  so we can start filling the database
	
	// --------------------------------------------------------------------------
	// Create table weather
	// Please note that drop command needs special permissions 
	
	if (!$mysqli->query("DROP TABLE IF EXISTS weatherdb") ||
    	!$mysqli->query("CREATE TABLE weatherdb(id INT, timestamp CHAR(20), name CHAR(20), location CHAR(20), brand CHAR(20), address CHAR(20), channel CHAR(8), temperature CHAR(8), humidity CHAR(8), windspeed CHAR(8), winddirection CHAR(8), rain INT )") )
	{
    	echo "Table creation weatherdb failed: (" . $mysqli->errno . ") " . $mysqli->error;
	}
	
	// This can esily become a VERY large datastructure
	// So we might have to be sure that the database table can accomodate the information
	for ($i=0; $i < count($weatherdb); $i++)
	{	
		if (!$mysqli->query("INSERT INTO weatherdb (id, timestamp, location, brand, address, channel, temperature, humidity, windspeed, winddirection, rain ) VALUES ('" 
					. $weatherdb[$i][id]. "','" 
					. $weatherdb[$i]['timestamp']. "','"
					. $weatherdb[$i][name]. "','"
					. $weatherdb[$i][location]. "','"
					. $weatherdb[$i][brand]. "','"
					. $weatherdb[$i][address]. "','"
					. $weatherdb[$i][channel]. "','"
					. $weatherdb[$i][temperature]. "','"
					. $weatherdb[$i][humidity]. "','"
					. $weatherdb[$i][windspeed]. "','"
					. $weatherdb[$i][winddirection]. "','"
					. $weatherdb[$i][rainfall]. "')"
					) 
			)
		{
			echo "Table Insert weatherdb failed: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
		}
	}
	return(1);
}


/*	=======================================================================================	
	Function to print a weather database 
	
	=======================================================================================	*/
function print_weatherdb($weatherdb)
{
	// var_dump(get_object_vars($cfg));
	echo " print weatherdb started succesfully, ";
	echo("Count of weatherdb: " . count($weatherdb) . "\n");
	
	for ($i=0; $i < count($weatherdb); $i++) 
	{
		echo("index: $i id: ".$weatherdb[$i]['id']
				.", location: ".$weatherdb[$i]['location']
				.", name: ".$weatherdb[$i][name]
				.", brand: ".$weatherdb[$i][brand]
				.", address: ".$weatherdb[$i][address]
				.", channel: ".$weatherdb[$i][channel]
				.", temperature: ".$weatherdb[$i][temperature]
				.", humidity: ".$weatherdb[$i][humidity]
				.", windspeed: ".$weatherdb[$i][windspeed]
				.", winddirection: ".$weatherdb[$i][winddirection]
				.", rainfall: ".$weatherdb[$i][rain]
				."\n");
	}
	echo("\n");
	
	echo("print_weatherdb:: Sucess\n\n");
}



/*	=======================================================================================	
	Function to get a directory listing of config for the client. 
	
	=======================================================================================	*/
function list_skin($dirname)
{
	global $apperr;
	global $appmsg;
	
	if ( $dirname == "" ) $apperr.="list_skin:: dirname is empty\n";
	
	$appmsg = glob($dirname . '*.css',GLOB_BRACE);
	$apperr += "list_skin:: glob returned: ";
	return(1);
}


/*	=======================================================================================	
	Function to get a directory listing of config for the client. 
	
	=======================================================================================	*/
function list_config($dirname)
{
	global $apperr;
	global $appmsg;
	
	if ( $dirname == "" ) $apperr.="list_config:: dirname is empty\n";
	
	$appmsg = glob($dirname . '*.cfg',GLOB_BRACE);
	$apperr += "list_config:: glob returned: ";
	return(1);
}


/*	=======================================================================================
	function post_parse()
	
	=======================================================================================	*/
function post_parse()
{
  global $appmsg;
  global $apperr;
  global $action;
  global $tim;
  global $icsmsg;
  
//  decho("Starting function post_parse";
		
	if (empty($_POST)) { 
		decho("No _post, ",1);
		return(-1);
	}
	foreach ( $_POST as $ind => $val )
	{
		switch ( $ind )
		{
			case "action":
				$action = $val;
			break;
			
			case "message":
				$icsmsg = $val;
//				$value  = json_encode($val);
			break;		
		} // switch $ind
	} // for
} // function

/*	=======================================================================================	
	function get_parse. 
	Parse the URL  $_GET for commands
	Commands may be load, message, style, debug
		In case of lamp, message contains its parameters
		
	The GET function comes in handy for command-line functions
	Use: http://localhost/kaku/backend_set.php?backup=1 for example
	=======================================================================================	*/
function get_parse() 
{
  global $appmsg;
  global $apperr;
  global $action;
  global $icsmsg;
  global $config_dir;
  global $log_dir;

  foreach ($_GET as $ind => $val )
  {
    decho ("get_parse:: index: $ind and val: $val<br>", 1);

    switch($ind) 
	{
	case "action":
		$action = $val;
	break;
	
	case "message":
		$icsmsg = json_decode($val);
		$apperr .= "\n ics: " . $icsmsg;
	break;

// ******* URL COMMANDLINE OPTIONS BELOW ***
	
	// Initial or repair load of th edatabase for LamPI
	// Need to specify this option on the URL: http://<my-lampi-url>/backend_set.php?load
	//
	case "load":
		echo "load:: config file: ".$config_dir."database.cfg\n" ;
		$cfg = read_database($config_dir."database.cfg");			// Load $cfg Object from File
		echo " cfg read; ";
		print_database($cfg);
		echo " now filling mysql; ";
		$ret = fill_database($cfg);							// Fill the MySQL Database with $cfg object
		echo " Making backup; ";
		$ret = file_database(($config_dir."newdbms.cfg"), $cfg);	// Make backup to other file
		echo " Backup newdbms.cfg made";
		if ($val < 1) {
			decho("file_database:: value must be >0");
		}	
		exit(0);
	break;	
	
	// Create the weather table (if not exist)
	//
	case "weather":
		echo "weather:: Start creating the database tables from ".$log_dir."LamPI-weather.log\n";
		$weatherdb = read_weatherdb($log_dir."LamPI-weather.log");
		$val = fill_weatherdb($weatherdb);
		if ($val < 1) {
			decho("fill_weatherdb:: value must be >0\n");
		}	
		echo "fill_weatherdb:: is complete\n";
		print_weatherdb($weatherdb);
		exit(0);
	break;
	
	case "store":
		$cfg = load_database();											// Fill $cfg from MySQL
		$ret = file_database($config_dir."newdbms.cfg", $cfg);			// Make backup to other file
		if ($val < 1) {
			decho("store:: value must be >0");
		}	
		echo "Backup is complete";
		exit(0);
	break;
	
    } //   Switch ind
  }	//  Foreach
  
  return(0);
  
} // Func


/*	=======================================================================================	
	MAIN PROGRAM

	=======================================================================================	*/

$ret = 0;
$cfg = 0;

// Parse the URL sent by client
// post_parse will parse the commands that are sent by the java app on the client
// $_POST is used for data that should not be sniffed from URL line, and
// for changes sent to the devices
$ret = post_parse();

// Parse the URL sent by client
// get_parse will parse more than just the commands that are sent by the java app on the client
// it will also respond to other $_GET commands if you call the php file directly
// The URL commands are shown in the get_parse function
if ($testing == 1) $ret = get_parse(); 

// Do Processing
// XXX Needs some cleaning and better/consistent messaging specification
// could also include the setting of debug on the client side
switch($action)
{
	case "load":
		$cfg = read_database($config_dir . "database.cfg");			// Load $cfg Object from File
		$ret = print_database($cfg);
		$ret = fill_database($cfg);									// Fill the MySQL Database with $cfg object
		$ret = file_database($config_dir . "newdbms.cfg", $cfg);	// Make backup to other file
	break;
	
	case "store":
		$cfg = load_database();										// Fill $cfg from MySQL
		$ret = file_database($config_dir . "database.cfg", $cfg);
	break;	
	
	case "list_config":												// Read the config directory
		$ret = list_config($config_dir);
		$apperr .= "list_config returned\n";
	break;	

	case "list_skin":												// Read the configfile into the system
		$ret = list_skin($skin_dir);
		$apperr .= "list_skin returned\n";
	break;	
	
	case "load_config":												// Read the configfile into the system
		$cfg = read_database($icsmsg);
		$ret = fill_database($cfg);
		$appmsg .= 'Success';										// Return code to calling client call
		$apperr .= "load_config of ".$icsmsg." returned OK";
		$ret = 1;
	break;
	
	case "store_config":											// Read the configfile into the system
		$cfg = load_database();
		$ret = file_database($config_dir.$icsmsg, $cfg);
		$appmsg .= 'Success';										// Return code to calling client call
		$apperr .= "store_config of ".$icsmsg." returned OK";
		$ret = 1;
	break;		
	
	default:
		$appmsg .= "action: ".$action;
		$apperr .= "\n<br />command not recognized: ".$action."\n";
		$ret = -1; 
}

if ($ret >= 0) 
{
	$send = array(
    	'tcnt' => $ret,												// Transaction count
		'appmsg'=> $appmsg,											// The return message for the calling client
    	'status' => 'OK',											// Status is OK
		'apperr'=> $apperr,											// error, debug of status messages
    );
	$output=json_encode($send);
}
else
{	
	$apperr .= "returns error code";
	$send = array(
    	'tcnt' => $ret,
		'appmsg'=> $appmsg,
    	'status' => 'ERR',
		'apperr' => $apperr,
    );
	$output=json_encode($send);
}
echo $output;
flush();

?>