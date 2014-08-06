<?php

require_once( '../daemon/backend_cfg.php' );
require_once( '../daemon/backend_lib.php' );
require_once( '../daemon/backend_sql.php' );

echo "LamPI-create is Starting";

$options=[];

if (! rrd_create("rrd_dbase.rrd",$options) )
{
	echo ("ERROR: Creating rrd database");
}


echo "LamPI-create is successful";


?>