#!/bin/sh

cp e350.rrd e350.org.rrd
rrdtool tune e350.rrd -a kw_hi_use:1
rrdtool tune e350.rrd -a kw_lo_use:1
rrdtool tune e350.rrd -a kw_hi_ret:1
rrdtool tune e350.rrd -a kw_lo_ret:1
rrdtool tune e350.rrd -a gas_use:1

rrdtool dump e350.rrd > effe.rrd
rm -rf e350.rrd
sleep 5
rm -rf e350.rrd
sleep 1
rrdtool restore effe.rrd e350.rrd -r

echo "Done"
