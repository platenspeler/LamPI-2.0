<?php 
define('__ROOT__', dirname(dirname(__FILE__)));
require_once(__ROOT__.'/frontend_cfg.php'); 
require_once(__ROOT__.'/frontend_lib.php' );
// LamPI, Javascript/jQuery GUI for controlling 434MHz devices (e.g. klikaanklikuit, action, alecto)
// Author: M. Westenberg (mw12554 @ hotmail.com)
// (c) M. Westenberg, all rights reserved
//
// Contributions:
//
// Version 1.6, Nov 10, 2013. Implemented connections, started with websockets option next (!) to .ajax calls.
// Version 1.7, Dec 10, 2013. Work on the mobile version of the program
// Version 1.8, Jan 18, 2014. Start support for (weather) sensors
// Version 1.9, Mar 10, 2014, Support for wired sensors and logging, and internet access ...
// Version 2.0, Jun 15, 2014, Initial support for Z-Wave devices through Razberry slave device.
//
// This is the code to animate the front-end of the application. The main screen is divided in 3 regions:
//
// Copyright, Use terms, Distribution etc.
// =========================================================================================
//  This software is licensed under GNU Public license as detailed in the root directory
//  of this distribution and on http://www.gnu.org/licenses/gpl.txt
//
//  The above copyright notice and this permission notice shall be included in
//  all copies or substantial portions of the Software.
// 
//  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
//  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
//  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
//  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
//  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
//  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
//  THE SOFTWARE.
//
//    You should have received a copy of the GNU General Public License
//    along with LamPI.  If not, see <http://www.gnu.org/licenses/>.
//
//
$log = new Logging();
$logfile='/home/pi/log/TemPI.log';
$log->lfile($logfile);
$log->lwrite("Starting graph.php script. __ROOT__ is ".__ROOT__);

$apperr = "";				// Global Error. Just append something and it will be sent back
$appmsg = "";				// Application Message (returned from backend to Client)
$graphAction = "";			// Must be "graph" only initially
$graphType = "";			// T emperature, H umidity, P airPressure
$graphPeriod = "";			// 1d 1w 1m 1y
$graphSensors = array();	// List of sensor values we like to graph


// ----------------------------------------------------------------------------
// MAKE GRAPH
// Ajax function for generating a graph .png file
//
function make_graph ($type, $period, $sensors)
{
	global $log, $logfile, $appmsg, $apperr;
	$rrd_dir='/home/pi/rrd/db/';
	$output='/home/pi/www/graphs/';
	$DEFpart='';
	$LINEpart='';
	$GRPINTpart='';
	$width='720';
	$height='360';
	$eol="";
	$sensorType="";
	$graphName="";
	$graphColor = array(
			0 => "111111",
			1 => "ff0000",
			2 => "00ff00",
			3 => "0000ff",
			4 => "ffff00",
			5 => "666666",
			6 => "00ffff"
			);				
	// Check which type of sensor we want to display and set the commands accordingly
	switch($type) {
		case 'T':
			$sensorType="temperature";
			$graphName="temp";
		break;
		case 'H':
			$sensorType="humidity";
			$graphName="humi";
		break;
		case 'P':
			$sensorType="airpressure";
			$graphName="press";
		break;
		default:
			$log->lwrite("make_graph:: Unknown graph type");
	}
	$log->lwrite("make_graph:: Starting .. type: ".$type.", period: ".$period);

	// Check on permissions for "graphs" directory. owner=pi, group=www-data, mode: 664
	
	
	// Check whether rrdtool exists
	
	// Make all definitions for the sensors, each sensor has its own DEF line
	// and its own LINE part  
	for ($i=0; $i< count($sensors); $i++) {
		if (($i + 1) == count($sensors) ) {
			$eol="\n";
			$log->lwrite("Setting newline for index ".$i);
		} else {
			$eol="";
		}
		
		$DEFpart .= 'DEF:t' . ($i+1) . '=' . $rrd_dir . $sensors[$i] . '.rrd:'.$sensorType.':AVERAGE ';
		// Update graph color based on colors in $graphColor array ($i modulus sizeof array)
		$LINEpart .= 'LINE2:t'. ($i+1) .'#'.$graphColor[$i % count($graphColor)].':"'. $sensors[$i].$eol.'" ';
		// this line is sensitive to correct syntax, especially the last part
		$GPRINTpart .= 'GPRINT:t'.($i+1).':LAST:"'.$sensors[$i].'\: %1.0lf C'.$eol.'" ';
	}
	
	// Build the exec string
	$exec_str = '/usr/bin/rrdtool graph '.$output.'all_'.$graphName.'_'.$period.'.png' ;
	$exec_str .= ' -s N-'.$period.' -a PNG -E --title="'.$sensorType.' readings" ';
	$exec_str .= '--vertical-label "'.$sensorType.'" --width '.$width.' --height '.$height.' ';
	$exec_str .= $DEFpart ;
	$exec_str .= $LINEpart ;
	$exec_str .= $GPRINTpart ;
	
	$log->lwrite($exec_str);
	$log->lwrite("Output of execution below");
	
	// The shell is executed and its output is directed to our logfile
	if (shell_exec($exec_str . " >> ".$logfile." 2>&1 && echo ' '")  === NULL ) {
			$log->lwrite("make_graph:: ERROR executing rrdtool");
			$apperr .= "\nERROR: generate_graphs ".$graphsPeriod."\n ";
			$ret = -1;
	}
	
	$log->lwrite("Command rrdtool executed");
	return(1);
}


/*	-------------------------------------------------------
*	function post_parse()
*	
*	-------------------------------------------------------	*/
function post_parse()
{
	global $log, $appmsg, $apperr;
	global $graphAction, $graphType, $graphPeriod, $graphSensors;	
	if (empty($_POST)) { 
		decho("call function post_parse without post",1);
		return(-1);
	}
	foreach ( $_POST as $ind => $val ) {
		switch ( $ind )
		{
			case "action":
				$graphAction =$val;			// switch val
			break;
			case "gtype":
				$graphType = $val;
			break;
			case "gperiod":
				$graphPeriod = $val;
			break;
			case "gsensors":
				$graphSensors = $val;
			break;
		} // switch $ind
	} // for
	return(1);
} // function



/*	=================================================================================	
										MAIN PROGRAM
	=================================================================================	*/

$ret = -1;



// Parse the URL sent by client
// post_parse will parse the commands that are sent by the java app on the client
// $_POST is used for data that should not be sniffed from URL line, and
// for changes sent to the devices

$log->lwrite("Starting Log record TemPI");
$ret = post_parse();

// Do Processing
switch($graphAction)
{
	// We generate a "STANDARD" temperature graph
	case "graph":
		$log->lwrite("graphs.php:: standard graph action chosen");
		$exec_str = 'cd /home/pi/rrd/scripts; /bin/sh ./generate_graphs.sh '.$graphPeriod.' ' ;
		$appmsg .= "graph.php:: execute ".$graphAction.", exec: <".$exec_str.">\n";
		if (shell_exec($exec_str . " 2>&1 && echo ' '")  === NULL ) {
		//if (shell_exec($exec_str . " >> /tmp/effe && echo ' '")  === NULL ) {
			$apperr .= "\nERROR: generate_graphs ".$graphsPeriod."\n ";
			$ret = -1;
		}
		else {
			$appmsg .="Success generate_graphs\n";
			$ret = 1;
		}
	break;
	
	// In case the user defines his own graph
	case "user":
		$log->lwrite("Starting User specific graphs, type: ".$graphType.", period: ".$graphPeriod);
		make_graph ($graphType,$graphPeriod,$graphSensors);
		$appmsg .="Success generate_graphs\n";
		$ret = 1;
	break;
	
	default:
		$appmsg .= ", action: ".$graphAction;
		$apperr .= ", graph: ".$graphAction.", command not recognized\n";
		$ret = -1; 
}

if ($ret >= 0) 
{
	$send = array(
		'tcnt' => $ret,
		'status' => 'OK',
		'result'=> $appmsg,
		'error'=> $apperr
    );
	$output=json_encode($send);
}
else
{	
	//$apperr .= $appmsg;
	$apperr .= "\nfunction returns error \n".$ret;
	$send = array(
    	'tcnt' => $ret,
    	'status' => 'ERR',
		'result'=> $appmsg,
		'error'=> $apperr
    );
	$output=json_encode($send);
}
$log->lwrite("Closing Log\n");
$log->lclose();
echo $output;
flush();

?>