rrdtool create heating.rrd \
--start N --step 60 \
DS:temp:GAUGE:600:00:95 \
RRA:MIN:0.5:1:1000 \
RRA:MAX:0.5:1:1000 \
RRA:AVERAGE:0.5:1:1000
