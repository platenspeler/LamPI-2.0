rrdtool create temp.rrd \
--start N --step 60 \
DS:temp1:GAUGE:600:-20:95 \
DS:temp2:GAUGE:600:-20:95 \
DS:temp3:GAUGE:600:-20:95 \
RRA:MIN:0.5:1:1000 \
RRA:MAX:0.5:1:1000 \
RRA:AVERAGE:0.5:1:1000
