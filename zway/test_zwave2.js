
require_once ( '../www/frontend_cfg.php' );
require_once ( '../www/frontend_lib.php' );

zway.devices[2].SensorBinary.data.level.bind(function() {
    if (this.value == false) {
        zway.devices[3].DoorLock.Set(255);
    }
});


