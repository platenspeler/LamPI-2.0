<?php 
require_once ( '../www/frontend_cfg.php' );
require_once ( '../www/frontend_lib.php' );



/*	======================================================================================	
	Note: Program to test Zwave equipment on Razberry
	Author: Maarten Westenberg
	Version 1.0 : August 16, 2013
	Version 1.3 : August 30, 2013
	Version 1.4 : 
	Version 1.5 : Oct 20, 2013
	Version 1.6 : Nov 10, 2013
	Version 1.7 : Dec 2013
	Version 1.9 : Jan 17, 2014

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

echo "curl_init start\n";
$ch = curl_init();
if ($ch == false) {
	echo "curl error";
}
echo "curl_init done\n";

curl_setopt_array (
	$ch, array (
	CURLOPT_URL => 'http://192.168.2.102:8083/ZAutomation/OpenRemote/SwitchMultilevelSet/2/0/90',
	CURLOPT_RETURNTRANSFER => true
	));

$output = curl_exec($ch);
if ($output == false) {
	echo "curl_exec returned false\n";
	return -1;
}

echo $output;
flush();

?>