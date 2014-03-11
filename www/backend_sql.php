<?php 
require_once( './backend_cfg.php' );
require_once( './backend_lib.php' );

/*	------------------------------------------------------------------------------	
	Note: Program to switch klikaanklikuit and coco equipment
	Author: Maarten Westenberg
	Version 1.0 : August 16, 2013
	Version 1.2 : August 30, 2013 removed all init function from file into a separate file
	Version 1.3 : September 6, 2013 Implementing first version of Daemon process
	Version 1.4 : Sep 20, 2013
	Version 1.5 : Oct 20, 2013
	Version 1.6 : NOv 10, 2013
	Version 1.7 : Dec 2013
	Version 1.8 : Jan 18, 2014

NOTE: Starting release 1.3 the functions in this file will be called by .php AJAX handlers
	of the client side AND by the LamPI-daemon.php process. As of release 1.4 probably parts
	of the ajax code will disappear in favor of socket communication to daemon that will
	then handle further requests.
	
NOTE:
	Start initiating the database by executing: http://localhost/coco/backend_set.php?load=1
	this will initialize the MySQP database as defined in init_dbase()
	
NOTE: This php file has NO memory other than what we store in SQL. This file is freshly
	called by the client for every database-like transaction. So do not expect arrays or other
	variables to have a certain value (based on previous actions)

Functions:
	load_database();			return code 1
	store_database; 			return code 2 reserved, not yet implemented 
	store_device($device);		return code 3 upon success, store new value of a device
	delete_device($device);		return code 4 upon succes, delete complete device
	store_scene($scene);		return code 8	
	add_room($room)				return code 7
	store_setting($setting);	return code 5 upon success
	add_device($device);		return code 6
	add_scene($scene)			return code 9
	delete_room($room)			return code 10
	delete_scene($scene)		return code 11
	add_timer($timer)			return code 12
	store_timer($timer);		return code 13
	
	-------------------------------------------------------------------------------	*/


$apperr = "";	// Global Error. Just append something and it will be sent back
$appmsg = "";	// Application Message (from backend to Client)


/* load_database() moved to backend_lib.php */

/*	-------------------------------------------------------
	function get_parse()
	
	-------------------------------------------------------	*/
function get_parse()
{
	global $appmsg;
	global $apperr;
	global $action;
	global $icsmsg;
  
//  decho("Starting function post_parse";	
	if (empty($_GET)) { 
		decho("No _GET, ",1);
		$apperr .= "get_parse:: empty _GET message\n";
		return(-1);
	}
	foreach ( $_GET as $ind => $val )
	{
		switch ( $ind )
		{
			case "action":
				$action = $val;
			break;
			
			case "message":
				$icsmsg = $val;
				//$icsmsg = json_decode($val);				// MMM Decode json message ecndoed by client
			break;
			
		} // switch $ind
	} // for
	return(0);
} // function



/*	-------------------------------------------------------
	function post_parse()
	
	-------------------------------------------------------	*/
function post_parse()
{
	global $appmsg;
	global $apperr;
	global $action;
	global $icsmsg;
  
//  decho("Starting function post_parse";	
	if (empty($_POST)) { 
		decho("No _post, ",1);
		$apperr .= "post_parse:: empty _POST message\n";
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
				//$icsmsg = json_decode($val);				// MMM Decode json message ecndoed by client
			break;
			
		} // switch $ind
	} // for
	return(0);
} // function


/*	=======================================================	
	MAIN PROGRAM

	=======================================================	*/

$ret = 0;

// Parse the URL sent by client
// post_parse will parse the commands that are sent by the java app on the client
// $_POST is used for data that should not be sniffed from URL line, and
// for changes sent to the devices

if ($debug>0) $ret = get_parse();
$ret = post_parse();

// Do Processing
// XXX Needs some cleaning and better/consistent messaging specification
// could also include the setting of debug on the client side
switch($action)
{	
	// Functions on the whole database
	case "load_database":
		$apperr .= "Load: msg: ".$icsmsg."\n";
		$appmsg = load_database();
		if ($appmsg == -1) { $ret = -1 ; $apperr .= "\nERROR Loading Database"; }
		else { $ret = 0; $apperr = "Configuration Loaded"; }

	break;
	case "store":
		// What happens if we receive a complex datastructure?
		$apperr .= "Calling store: icsmsg: $icsmsg\n";
		$appmsg = store_database($icsmsg);
		$apperr .= "\nStore ";
		$ret = 0;
	break;
	
	// Store the complete devices object at once
	case "store_device":
		$tcnt = store_device($icsmsg);
		$apperr .= "\nStore device \n";
		$ret = $tcnt;
	break;
	case "delete_device":
		$tcnt = delete_device($icsmsg);		
		$apperr .= "\nDelete device \n";
		$ret = $tcnt; 
	break;
	case "add_device":	
		$apperr .= "Calling add_device:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = add_device($icsmsg);
		$apperr .= "\nAdd device ";
		$ret = $tcnt;
	break;	
	
	// Room commands
	case "add_room":	
		$apperr .= "Calling add_room:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = add_room($icsmsg);
		$apperr .= "\nAdd room ";
		$ret = $tcnt;
	break;
	case "delete_room":	
		$apperr .= "Calling delete_room:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = delete_room($icsmsg);
		$apperr .= "\ndelete room ";
		$ret = $tcnt;
	break;
	
	// Scene commands
	case "add_scene":	
		$apperr .= "Calling add_scene:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = add_scene($icsmsg);
		$apperr .= "\nScene added ";
		$ret = $tcnt;
	break;
	case "upd_scene":
		$apperr .= "Calling upd_scene:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = store_scene($icsmsg);
		$apperr .= "\nScene updated ";
		$ret = $tcnt;
	break;
	case "store_scene":
		$apperr .= "Calling store_scene:: jsonmsg: ".jason_decode($icsmsg)."\n";
		$tcnt = store_scene($icsmsg);
		$apperr .= "\nScene stored ";
		$ret = $tcnt; 
	break;	
	case "delete_scene":
		$apperr .= "Calling delete_scene:: json: ".json_decode($icsmsg)."\n";
		$tcnt = delete_scene($icsmsg);		
		$apperr .= "\nScene deleted ";
		$ret = $tcnt; 
	break;
	
	// Timer database functions
	case "add_timer":	
		$apperr .= "Calling add_timer:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = add_timer($icsmsg);
		$apperr .= "\nAdd timer ";
		$ret = $tcnt;
	break;
	case "store_timer":	
		$apperr .= "Calling store_timer:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = store_timer($icsmsg);
		$apperr .= "\nStore timer ";
		$ret = $tcnt;
	break;
	case "delete_timer":	
		$apperr .= "Calling delete_timer:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = delete_timer($icsmsg);
		$apperr .= "\nDelete timer ";
		$ret = $tcnt;
	break;
	
	// Handset Functions
	//
	case "add_handset":	
		$apperr .= "Calling add_handset:: icsmsg: ".$icsmsg.", json: ".json_decode($icsmsg)."\n";
		$tcnt = add_handset($icsmsg);
		$apperr .= "\nAdd handset ";
		$ret = $tcnt;
	break;
	case "store_handset":
		$apperr .= "Calling store_handset:: icsmsg json: ".json_decode($icsmsg)."\n";
		$tcnt = store_handset($icsmsg);
		$appmsg = " Handset stored ";
		$ret = $tcnt; 
	break;	
	case "delete_handset":
		$apperr .= "Calling delete_handset:: json: ".json_decode($icsmsg)."\n";
		$tcnt = delete_handset($icsmsg);		
		$apperr .= "\nHandset deleted ";
		$ret = $tcnt; 
	break;
	
	// Store the complete settings array at once.
	// There may not be a reason to load settings, as we load setting
	// during init.
	case "store_setting":
		$apperr .= "Calling store_setting:: icsmsg: $icsmsg\n";
		$tcnt = store_setting($icsmsg);		
		$apperr .= "\nSetting updated ";
		$ret = $tcnt; 
	break;	
	
	// If the command is not defined above, this is treated as a error
	default:
		$appmsg .= "action: ".$action;
		$apperr .= "\n<br />default, command not recognized: ,".$action.",\n";
		$ret = -1; 
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
echo $output;
flush();

?>