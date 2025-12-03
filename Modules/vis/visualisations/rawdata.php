<?php
    /*
    All Emoncms code is released under the GNU Affero General Public License.
    See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
    */
    
/**
 * Raw Data Visualization Template
 * 
 * This file renders a standard time-series graph visualization for a single feed.
 * It displays raw feed data with customizable options for styling, scaling, and data processing.
 * 
 * PHP Template Section:
 * ====================
 * This section (lines 1-55) handles:
 * 1. Security check and global variable access
 * 2. JavaScript library includes (Flot.js, Feed API, helpers)
 * 3. HTML structure generation (graph container, controls, statistics panel)
 * 4. Translation string output for UI elements
 * 
 * Parameters Received from Controller:
 * ====================================
 * The vis_controller.php passes validated parameters in the $array variable:
 * - $feedid: Feed ID (integer, validated)
 * - $feedidname: Feed name (string, for display)
 * - $apikey: API key for Feed API authentication
 * - $embed: Embed mode (0=normal, 1=fullscreen)
 * - $valid: Validation status ("true" or error message)
 * - Optional URL parameters: colour, colourbg, units, dp, scale, average, delta, skipmissing, fill
 * 
 * Template Structure:
 * ==================
 * 1. Security: Checks EMONCMS_EXEC constant to prevent direct access
 * 2. Globals: Accesses $path (base URL), $embed (embed mode), $vis_version (cache busting)
 * 3. Libraries: Loads Flot.js and dependencies, Feed API, helper functions
 * 4. HTML: Creates graph container with navigation controls and statistics panel
 * 5. JavaScript: Embedded script (see JavaScript section below) handles data fetching and rendering
 * 
 * Dependencies:
 * ============
 * - Flot.js: jQuery-based plotting library
 * - feed.js: Feed API wrapper for data fetching
 * - vis.helper.js: View manipulation and helper functions
 * - excanvas.min.js: IE8 compatibility for canvas
 */
    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $embed, $vis_version;
?>

<!-- Internet Explorer 8 compatibility: excanvas provides canvas support -->
<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->

<!-- Flot.js plotting library and plugins -->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.merged.js"></script>

<!-- Feed API wrapper: Provides feed.getdata() function for fetching time-series data -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/feed/feed.js?v=<?php echo $vis_version; ?>"></script>

<!-- Visualization helper functions: view manipulation, tooltips, statistics -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/vis.helper.js?v=<?php echo $vis_version; ?>"></script>

<!-- Visualization title container (populated by JavaScript if not embedded) -->
<div id="vis-title"></div>

<!-- Main graph container with controls and statistics overlay -->
<div id="graph_bound" style="height:400px; width:100%; position:relative; ">
    <!-- Graph canvas: Flot.js will render the chart here -->
    <div id="graph"></div>
    
    <!-- Navigation and control buttons (positioned absolutely over graph) -->
    <div id="graph-buttons" style="position:absolute; top:18px; right:32px; opacity:0.5;">
        <!-- Time window presets: Day, Week, Month, Year -->
        <div class='btn-group'>
            <button class='btn graph-time' type='button' time='1'>D</button>
            <button class='btn graph-time' type='button' time='7'>W</button>
            <button class='btn graph-time' type='button' time='30'>M</button>
            <button class='btn graph-time' type='button' time='365'>Y</button>
        </div>

        <!-- Zoom and pan controls (hidden by default, shown on hover) -->
        <div class='btn-group' id='graph-navbar' style='display: none;'>
            <button class='btn graph-nav' id='zoomin'>+</button>
            <button class='btn graph-nav' id='zoomout'>-</button>
            <button class='btn graph-nav' id='left'><</button>
            <button class='btn graph-nav' id='right'>></button>
        </div>

        <!-- Fullscreen toggle -->
        <div class='btn-group'>
            <button class='btn graph-exp' id='graph-fullscreen' type='button'><i class='icon-resize-full'></i></button>
        </div>
    </div>
    
    <!-- Statistics overlay: Shows current value at cursor position -->
    <h3 style="position:absolute; top:0px; left:32px;"><span id="stats"></span></h3>
</div>

<!-- Statistics panel: Detailed statistics for current view (hidden by default) -->
<div id="info" style="padding:20px; margin:25px; background-color:rgb(245,245,245); font-style:italic; display:none">
    <!-- Statistics are populated by JavaScript draw() function -->
    <p><b><?php echo tr("Mean:") ?></b> <span id="stats-mean"></span></p>
    <p><b><?php echo tr("Min:") ?></b> <span id="stats-min"></span></p>
    <p><b><?php echo tr("Max:") ?></b> <span id="stats-max"></span></p>
    <p><b><?php echo tr("Standard deviation:") ?></b> <span id="stats-stdev"></span></p>
    <p><b><?php echo tr("Datapoints in view:") ?></b> <span id="stats-npoints"></span></p>
</div>

<script id="source" language="javascript" type="text/javascript">
/**
 * Raw Data Visualization - Embedded JavaScript
 * 
 * This script handles rendering a single feed's raw time-series data.
 * It provides interactive zooming, panning, and statistical analysis.
 * 
 * Data Flow:
 * 1. User interaction (zoom/pan/time selection) triggers draw()
 * 2. draw() calculates optimal interval and calls feed.getdata()
 * 3. feed.getdata() makes synchronous AJAX request to Feed API
 * 4. Data is processed (scaled if needed) and statistics calculated
 * 5. plot() renders the graph using Flot.js
 * 
 * Note: This uses SYNCHRONOUS feed.getdata() call (no callback)
 * The request blocks until data is received.
 * 
 * Error Handling:
 * - feed.getdata() returns empty array on error
 * - Statistics functions handle empty data gracefully
 * - Graph simply won't display if no data received
 */

// Extract parameters from PHP (set by vis_controller.php)
var feedid = <?php echo $feedid; ?>;              // Feed ID number
var feedname = "<?php echo $feedidname; ?>";      // Feed name for display
var apikey = "<?php echo $apikey; ?>";            // API key for Feed API authentication
feed.apikey = apikey;                              // Set API key in feed object
var embed = <?php echo $embed; ?>;                 // Embed mode: 0=normal, 1=fullscreen
var valid = "<?php echo $valid; ?>";               // Validation status from controller
var previousPoint = false;                        // Track last hovered point for tooltip

var plotColour = urlParams.colour;
    if (plotColour==undefined || plotColour=='') plotColour = "EDC240";

var backgroundColour = urlParams.colourbg;
if (backgroundColour==undefined || backgroundColour=='') backgroundColour = "ffffff";
$("body").css("background-color","#"+backgroundColour);
document.body.style.setProperty("--bg-vis-graph-color", "#"+backgroundColour);
  
var units = urlParams.units;
    if (units==undefined || units=='') units = "";
var dp = urlParams.dp;
    if (dp==undefined || dp=='') dp = 1;
var scale = urlParams.scale;
    if (scale==undefined || scale=='') scale = 1;
var average = urlParams.average;
    if (average==undefined || average=='') average = 0;
var skipmissing = urlParams.skipmissing;
    if (skipmissing==undefined || skipmissing=='') skipmissing = 1;
var delta = urlParams.delta;
    if (delta==undefined || delta=='') delta = 0;
var fill = +urlParams.fill;
    if (fill==undefined || fill=='') fill = 0;
    if (fill>0) fill = true;
var initzoom = urlParams.initzoom;
    if (initzoom==undefined || initzoom=='' || initzoom < 1) initzoom = '7'; // Initial zoom default to 7 days (1 week)
// Some browsers want the colour codes to be prepended with a "#". Therefore, we
// add one if it's not already there
if (plotColour.indexOf("#") == -1) {
    plotColour = "#" + plotColour;
}

var top_offset = 0;
var placeholder_bound = $('#graph_bound');
var placeholder = $('#graph');

var width = placeholder_bound.width();
var height = width * 0.5;

placeholder.width(width);
placeholder_bound.height(height);
placeholder.height(height-top_offset);

if (embed) placeholder.height($(window).height()-top_offset);

var timeWindow = (3600000*24.0*initzoom);
view.start = +new Date - timeWindow;
view.end = +new Date;
view.limit_x = false;

var data = [];

$(function() {

    if (embed==false) {
        $("#vis-title").html("<h2><?php echo tr("Raw:") ?> "+feedname+"<h2>");
        $("#info").show();
    }
    draw();
    
    $("#zoomout").click(function () {view.zoomout(); draw();});
    $("#zoomin").click(function () {view.zoomin(); draw();});
    $('#right').click(function () {view.panright(); draw();});
    $('#left').click(function () {view.panleft(); draw();});
    $("#graph-fullscreen").click(function () {view.fullscreen();});
    $('.graph-time').click(function () {view.timewindow($(this).attr("time")); draw();});
    
    placeholder.bind("plotselected", function (event, ranges)
    {
        view.start = ranges.xaxis.from;
        view.end = ranges.xaxis.to;
        draw();
    });

    placeholder.bind("plothover", function (event, pos, item)
    {
        if (item) {
            //var datestr = (new Date(item.datapoint[0])).format("ddd, mmm dS, yyyy");
            //$("#stats").html(datestr);
            if (previousPoint != item.datapoint)
            {
                previousPoint = item.datapoint;

                $("#tooltip").remove();
                var itemTime = item.datapoint[0];
                var itemVal = item.datapoint[1];

                // I'd like to eventually add colour hinting to the background of the tooltop.
                // This is why showTooltip has the bgColour parameter.
                tooltip(item.pageX, item.pageY, itemVal.toFixed(dp) + " " + units, "#DDDDDD");
            }
        }
        else
        {
            $("#tooltip").remove();
            previousPoint = null;
        }
    });

    /**
     * Fetches and processes feed data, then renders the graph.
     * 
     * This function is called whenever the time window changes (zoom, pan, time selection).
     * It:
     * 1. Calculates optimal data interval based on time window
     * 2. Fetches data synchronously from Feed API
     * 3. Applies scaling if configured
     * 4. Calculates statistics (mean, min, max, std dev)
     * 5. Updates UI with statistics
     * 6. Renders the graph
     * 
     * AJAX Pattern:
     * - Uses SYNCHRONOUS feed.getdata() call (blocks until data received)
     * - No callback function - data is returned directly
     * - Returns empty array [] on error (check data.length)
     * 
     * Error Handling:
     * - feed.getdata() returns [] on error (check data.length > 0)
     * - stats() function handles empty arrays gracefully
     * - Graph won't display if no data (empty plot)
     */
    function draw()
    {   
        // Calculate optimal data interval: aim for ~2400 data points
        // This balances detail vs. performance
        view.calc_interval(2400);
        
        /**
         * Synchronous AJAX call to fetch feed data
         * 
         * feed.getdata() Parameters:
         * @param {number} feedid - Feed ID to fetch
         * @param {number} view.start - Start timestamp (milliseconds)
         * @param {number} view.end - End timestamp (milliseconds)
         * @param {number} view.interval - Data interval in seconds (calculated above)
         * @param {number} average - Average mode: 0=off, 1=on
         * @param {number} delta - Delta mode: 0=off, 1=on (calculate differences)
         * @param {number} skipmissing - Skip missing data: 0=include nulls, 1=skip nulls
         * @param {number} 1 - Limit interval mode: enabled
         * 
         * Returns: Array of [timestamp, value] pairs
         *   Format: [[timestamp1, value1], [timestamp2, value2], ...]
         *   Returns [] (empty array) on error
         * 
         * Note: This is SYNCHRONOUS - execution blocks until data is received
         * For async version, use feed.getdata() with callback parameter
         */
        data = feed.getdata(feedid,view.start,view.end,view.interval,average,delta,skipmissing,1);
        
        // Apply scaling if configured (scale != 1)
        // This allows unit conversion (e.g., W to kW)
        var out = [];
        if (scale!=1) {
            for (var z=0; z<data.length; z++) {
                var val = data[z][1] * scale;  // Multiply value by scale factor
                out.push([data[z][0],val]);    // Keep timestamp, scale value
            }
            data = out;
        } 
       
        // Calculate statistics from data
        // stats() function is defined in vis.helper.js
        var s = stats(data);
        
        // Update statistics display in UI
        $("#stats-mean").html(s.mean.toFixed(dp)+units);      // Mean value
        $("#stats-min").html(s.minval.toFixed(dp)+units);     // Minimum value
        $("#stats-max").html(s.maxval.toFixed(dp)+units);     // Maximum value
        $("#stats-stdev").html(s.stdev.toFixed(dp)+units);    // Standard deviation
        $("#stats-npoints").html(data.length);                 // Number of data points
        
        // Render the graph with processed data
        plot();
    }
    
    /**
     * Renders the graph using Flot.js plotting library.
     * 
     * Creates a single line series from the data array and renders it
     * with the configured styling options.
     * 
     * @see data - Global array of [timestamp, value] pairs, set by draw()
     * @see plotColour - Global color variable, set from URL parameters
     * @see fill - Global fill option, determines if area under line is filled
     */
    function plot()
    {
        var options = {
            canvas: true,                    // Use HTML5 canvas for rendering
            lines: { fill: fill },            // Fill area under line if enabled
            xaxis: { 
                mode: "time",                // X-axis displays time
                timezone: "browser",         // Use browser's timezone
                min: view.start,             // Minimum time
                max: view.end,               // Maximum time
                minTickSize: [view.interval, "second"]  // Minimum tick spacing
            },
            //yaxis: { min: 0 },              // Optional: uncomment to set Y-axis minimum
            grid: {
                hoverable: true,             // Enable hover tooltips
                clickable: true              // Enable click selection
            },
            selection: { mode: "x" },         // Allow selecting time range by dragging
            touch: { 
                pan: "x",                    // Allow panning on touch devices (x-axis)
                scale: "x"                   // Allow pinch-to-zoom (x-axis)
            }
        }

        // Render single line series with data and color
        $.plot(placeholder, [{data:data,color: plotColour}], options);
    }

    
    // Graph buttons and navigation efects for mouse and touch
    $("#graph").mouseenter(function(){
        $("#graph-navbar").show();
        $("#graph-buttons").stop().fadeIn();
        $("#stats").stop().fadeIn();
    });
    $("#graph_bound").mouseleave(function(){
        $("#graph-buttons").stop().fadeOut();
        $("#stats").stop().fadeOut();
    });
    $("#graph").bind("touchstarted", function (event, pos)
    {
        $("#graph-navbar").hide();
        $("#graph-buttons").stop().fadeOut();
        $("#stats").stop().fadeOut();
    });
    
    $("#graph").bind("touchended", function (event, ranges)
    {
        $("#graph-buttons").stop().fadeIn();
        $("#stats").stop().fadeIn();
        view.start = ranges.xaxis.from; 
        view.end = ranges.xaxis.to;
        draw();
    });

    $(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse',vis_resize);
    
    function vis_resize() {
        var width = placeholder_bound.width();
        var height = width * 0.5;

        placeholder.width(width);
        placeholder_bound.height(height);
        placeholder.height(height-top_offset);

        if (embed) placeholder.height($(window).height()-top_offset);
        plot();
    }
    
});
</script>
