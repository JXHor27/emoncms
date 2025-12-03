<?php
/*
    All Emoncms code is released under the GNU General Public License v3.
    See COPYRIGHT.txt and LICENSE.txt.
    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project: http://openenergymonitor.org
*/

/**
 * Stacked Visualization Template
 * 
 * This file renders a stacked area chart comparing two feeds (bottom and top).
 * Uses Flot.js stack plugin to create layered area visualization.
 * 
 * PHP Template Section:
 * ====================
 * This section handles:
 * 1. Security check and global variable access
 * 2. JavaScript library includes (Flot.js, stack plugin, Feed API, date utilities, helpers)
 * 3. HTML structure generation (stacked chart container, loading overlay)
 * 4. Conditional title display
 * 
 * Parameters Received from Controller:
 * ====================================
 * - $bottom: Bottom feed ID (integer, validated, accepts realtime or daily feed)
 * - $top: Top feed ID (integer, validated, accepts realtime or daily feed)
 * - $apikey: API key for Feed API authentication
 * - $embed: Embed mode (0=normal, 1=fullscreen)
 * - Optional URL parameters: colourt (top color), colourb (bottom color), delta
 * 
 * Key Features:
 * ============
 * - Stacked area chart: Two feeds displayed as stacked layers
 * - Color customization: Separate colors for top and bottom layers
 * - Loading indicator: Shows overlay while data is being fetched
 * - Date range utilities: Uses daysmonthsyears.js for time calculations
 */
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $embed, $vis_version;
?>
<!-- Internet Explorer 8 compatibility -->
<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->

<!-- Flot.js plotting library -->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.merged.js"></script>

<!-- Flot.js stack plugin: Enables stacked area chart rendering -->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.stack.min.js"></script>

<!-- Feed API wrapper: Provides feed.getdata() function -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/feed/feed.js?v=<?php echo $vis_version; ?>"></script>

<!-- Visualization helper functions: view manipulation -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/vis.helper.js?v=<?php echo $vis_version; ?>"></script>

<!-- Date utility functions: Used for calculating days, months, years ranges -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/vis/visualisations/common/daysmonthsyears.js?v=2"></script>

<!-- Conditional title: Only show if not embedded -->
<?php if (!$embed) { ?>
<h2><?php echo tr("Stacked"); ?></h2>
<?php } ?>

<!-- Main stacked chart container -->
<div id="graph_bound" style="width:100%; height:400px; position:relative; ">
    <!-- Stacked area chart canvas: Flot.js will render stacked layers here -->
    <div id="graph"></div>
    
    <!-- Loading overlay: Shown while data is being fetched -->
    <div id="loading" style="position:absolute; top:0px; left:0px; width:100%; height:100%; background-color: rgba(255,255,255,0.5);"></div>
    
    <!-- Status display: Shows current view information -->
    <h2 style="position:absolute; top:0px; left:40px;"><span id="out"></span></h2>
</div>

<script id="source" language="javascript" type="text/javascript">
var kwhdA = <?php echo $bottom; ?>;
var kwhdB = <?php echo $top; ?>;
var delta = <?php echo $delta; ?>;

var colourb = urlParams.colourb;
if (colourb==undefined || colourb=='') colourb = "0096ff";
var colourt = urlParams.colourt;
if (colourt==undefined || colourt=='') colourt = "7cc9ff";

// Some browsers want the colour codes to be prepended with a "#". Therefore, we
// add one if it's not already there
if (colourb.indexOf("#") == -1) {
    colourb = "#" + colourb;
}
if (colourt.indexOf("#") == -1) {
    colourt = "#" + colourt;
}

var apikey = "<?php echo $apikey; ?>";
feed.apikey = apikey;

var timeWindow = (3600000*24.0*365*5); //Initial time window
var start = +new Date - timeWindow;    //Get start time
var end = +new Date; 

var d = new Date()
var n = d.getTimezoneOffset();
var offset = n / -60;
start = Math.floor(start / 86400000) * 86400000;
end = Math.floor(end / 86400000) * 86400000;
start -= offset * 3600000;
end -= offset * 3600000;

var dataA = feed.getdata(kwhdA,start,end,"daily",0,delta,0,0);
var dataB = feed.getdata(kwhdB,start,end,"daily",0,delta,0,0);

var embed = <?php echo $embed; ?>;
$('#graph').width($('#graph_bound').width());
$('#graph').height($('#graph_bound').height());
if (embed) $('#graph').height($(window).height());

$('#loading').hide();
var view = 0;

var daysA = [];
var daysB = [];

var monthsA = get_months(dataA);
var monthsB = get_months(dataB);

$(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse',vis_resize);

function vis_resize() {
    $('#graph').width($('#graph_bound').width());
    if (embed) $('#graph').height($(window).height());
    bargraph(monthsA.data,monthsB.data,3600*24*20,"month");
}

bargraph(monthsA.data,monthsB.data,3600*24*20,"month");

$("#graph").bind("plotclick", function (event, pos, item){
    if (item!=null) {
        if (view==0) {
            var d = new Date();
            d.setTime(item.datapoint[0]);
            daysA = get_days_month(dataA,d.getMonth(),d.getFullYear());
            daysB = get_days_month(dataB,d.getMonth(),d.getFullYear());
            bargraph(daysA,daysB,3600*22,"day");
            view = 1;
            $("#out").html("");
        }
    } else {
        if (view==1) { $("#out").html(""); view = 0; bargraph(monthsA.data,monthsB.data,3600*24*20,"month"); }
        if (view==2) { $("#out").html(""); view = 1; bargraph(daysA,daysB,3600*22,"day"); }
    }
});

$("#graph").bind("plothover", function (event, pos, item) {
    if (item!=null) {
        var d = new Date();
        d.setTime(item.datapoint[0]);
        var mdate = new Date(item.datapoint[0]);
        if (item.series.data[item.dataIndex]!=undefined) {
            var value = item.series.data[item.dataIndex][1];
            if (view==0) $("#out").html(value.toFixed(1)+" kWh/month | "+mdate.format("mmm yyyy"));
            if (view==1) $("#out").html(value.toFixed(1)+" kWh/d | "+mdate.format("dS mmm yyyy"));
        }
    }
});

function bargraph(dataA,dataB,barwidth, mode) {
    $.plot($("#graph"), [ {color: colourb, data:dataA}, {color: colourt, data:dataB}],{
        canvas: true,
        series: {
            stack: true,
            bars: { show: true,align: "center",barWidth: (barwidth*1000),fill: true }
        },
        grid: { show: true, hoverable: true, clickable: true },
        xaxis: { mode: "time", timezone: "browser", minTickSize: [1, mode], tickLength: 1 },
        touch: { pan: "x", scale: "x" }
    });
}
</script>
