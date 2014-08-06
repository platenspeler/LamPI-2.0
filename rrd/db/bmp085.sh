rrdtool create bmp085.rrd \
--start N --step 180 \
DS:temp:GAUGE:600:00:95 \
DS:pressure:GAUGE:600:0:1200 \
DS:altitude:GAUGE:600:-100:5000 \
DS:sealevel:GAUGE:600:0:1200 \
RRA:MIN:0.5:1:1000 \
RRA:MAX:0.5:1:1000 \
RRA:AVERAGE:0.5:1:500 \
RRA:AVERAGE:0.5:20:720 
