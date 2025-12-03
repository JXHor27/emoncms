<?php
/*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

   Emoncms - open source energy visualisation
   Part of the OpenEnergyMonitor project: http://openenergymonitor.org
*/

/**
 * Realtime Visualization Template
 * 
 * This file renders a real-time streaming graph visualization that continuously
 * updates with the latest feed data. Designed for live monitoring scenarios.
 * 
 * PHP Template Section:
 * ====================
 * This section (lines 1-31) handles:
 * 1. Security check and global variable access
 * 2. JavaScript library includes (Flot.js, Feed API, helpers)
 * 3. HTML structure generation (graph container, time window controls)
 * 4. Conditional title display (hidden when embedded)
 * 
 * Parameters Received from Controller:
 * ====================================
 * The vis_controller.php passes validated parameters:
 * - $feedid: Feed ID (integer, validated, must be realtime feed)
 * - $feedidname: Feed name (string, for display)
 * - $apikey: API key for Feed API authentication
 * - $embed: Embed mode (0=normal, 1=fullscreen)
 * - $kw: Boolean flag for kW conversion (0 or 1)
 * - Optional URL parameters: colour, colourbg
 * 
 * Template Structure:
 * ==================
 * 1. Security: Checks EMONCMS_EXEC constant
 * 2. Globals: Accesses $path, $embed, $vis_version
 * 3. Libraries: Loads Flot.js, Feed API, helper functions
 * 4. Conditional Title: Shows feed name only if not embedded
 * 5. Graph Container: Creates canvas with time window selection buttons
 * 6. JavaScript: Embedded script handles continuous data updates
 * 
 * Key Differences from RawData:
 * =============================
 * - Simpler UI: Only time window buttons, no zoom/pan controls
 * - Real-time updates: JavaScript continuously fetches latest data point
 * - GPU-optimized: Uses requestAnimationFrame for smooth rendering
 * - Dynamic refresh: Update rate adjusts based on time window
 */
    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $embed, $vis_version;
 ?>

<!-- Internet Explorer 8 compatibility: excanvas provides canvas support -->
<!--[if IE]><script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/flot/excanvas.min.js"></script><![endif]-->

<!-- Flot.js plotting library -->
<script language="javascript" type="text/javascript" src="<?php echo $path; ?>Lib/flot/jquery.flot.merged.js"></script>

<!-- Visualization helper functions: view manipulation, time window handling -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Lib/vis.helper.js?v=<?php echo $vis_version; ?>"></script>

<!-- Feed API wrapper: Provides feed.getdata() and feed.get_timevalue() functions -->
<script language="javascript" type="text/javascript" src="<?php echo $path;?>Modules/feed/feed.js?v=<?php echo $vis_version; ?>"></script>
    
<!-- Conditional title: Only show if not embedded (embed=0) -->
<?php if (!$embed) { ?>
<h2><?php echo tr("Realtime data:"); ?> <?php echo $feedidname; ?></h2>
<?php } ?>

<!-- Main graph container -->
<div id="graph_bound" style="height:400px; width:100%; position:relative; ">
    <!-- Graph canvas: Flot.js will render the streaming chart here -->
    <div id="graph"></div>
    
    <!-- Time window selection buttons: Adjusts the time range displayed -->
    <!-- Values are in seconds: 3600=1 hour, 1800=30 min, 900=15 min, 300=5 min, 60=1 min -->
    <div style="position:absolute; top:20px; right:20px;  opacity:0.5;">
        <button class="viewWindow" time="3600">1 <?php echo tr('hour') ?></button>
        <button class="viewWindow" time="1800">30 <?php echo tr('min') ?></button>
        <button class="viewWindow" time="900">15 <?php echo tr('min') ?></button>
        <button class="viewWindow" time="300">5 <?php echo tr('min') ?></button>
        <button class="viewWindow" time="60">1 <?php echo tr('min') ?></button>
    </div>
</div>

<script id="source" language="javascript" type="text/javascript">
/**
 * Realtime Visualization - Embedded JavaScript
 * 
 * This script handles real-time streaming visualization of feed data.
 * It continuously updates the graph with the latest data points, creating
 * a live monitoring experience.
 * 
 * Update Strategy:
 * 1. Initial load: Synchronous feed.getdata() fetches historical data
 * 2. Continuous updates: Periodic AJAX calls (getdp()) fetch latest data point
 * 3. Rendering: GPU-friendly requestAnimationFrame loop updates display
 * 4. Dynamic refresh: Update rate adjusts based on time window size
 * 
 * Data Flow:
 * - Initial: feed.getdata() → data array → plot()
 * - Updates: getdp() → adds new point to data → plot() (via fast())
 * - Rendering: gpu_fast() loop → fast() → plot() (10-20 FPS)
 * 
 * Performance:
 * - Uses requestAnimationFrame for smooth GPU-accelerated rendering
 * - Adjusts refresh rate based on time window (longer windows = slower updates)
 * - Removes old data points outside time window to prevent memory growth
 * 
 * Error Handling:
 * - AJAX errors in getdp() are silent (data simply won't update)
 * - feed.getdata() returns [] on error (check data.length)
 * - Missing data points are handled gracefully
 */

// Extract parameters from PHP (set by vis_controller.php)
var feedid = <?php echo $feedid; ?>;              // Feed ID number
var apikey = "<?php echo $apikey; ?>";            // API key for Feed API authentication
feed.apikey = apikey;                              // Set API key in feed object
var embed = <?php echo $embed; ?>;                 // Embed mode: 0=normal, 1=fullscreen
var is_kw = <?php echo $kw === 1?'true':'false'; ?>;  // Convert watts to kilowatts if true
var data = [];                                      // Array of [timestamp, value] pairs
var timerget;                                       // Interval ID for periodic data fetching
var fast_update_fps = 10;                          // Frames per second for rendering loop

var plotColour = urlParams.colour;
if (plotColour == undefined || plotColour == '') plotColour = "EDC240";

if (plotColour.indexOf("#") == -1) {
    plotColour = "#" + plotColour;
}

var backgroundColour = urlParams.colourbg;
if (backgroundColour == undefined || backgroundColour == '') backgroundColour = "ffffff";
$("body").css("background-color", "#" + backgroundColour);

var initzoom = urlParams.initzoom;
if (initzoom == undefined || initzoom == '' || initzoom < 1) initzoom = '15'; // Initial zoom default to 15 mins

var timeWindow = (60 * 1000 * initzoom); //Initial time window

var graph_bound = $('#graph_bound'),
    graph = $("#graph");
graph.width(graph_bound.width()).height(graph_bound.height());
if (embed) graph.height($(window).height());

// Initialize time window: current time minus timeWindow duration
var now = (new Date()).getTime();
var start = now - timeWindow;  // Start time (timeWindow ago)
var end = now;                  // End time (current time)

// Calculate optimal data interval: aim for ~800 data points
// This balances detail vs. performance for real-time display
var interval = parseInt(((end * 0.001 + 10) - (start * 0.001 - 10)) / 800);

/**
 * Initial data load: Fetch historical data synchronously
 * 
 * feed.getdata() Parameters:
 * @param {number} feedid - Feed ID to fetch
 * @param {number} start - 10000 - Start time (10 seconds before window for padding)
 * @param {number} end + 10000 - End time (10 seconds after window for padding)
 * @param {number} interval - Data interval in seconds (calculated above)
 * @param {number} 0 - Average mode: disabled
 * @param {number} 0 - Delta mode: disabled
 * @param {number} 1 - Skip missing data: enabled
 * @param {number} 1 - Limit interval mode: enabled
 * 
 * Returns: Array of [timestamp, value] pairs
 * Note: This is SYNCHRONOUS - blocks until data received
 */
data = feed.getdata(feedid, (start - 10000), (end + 10000), interval, 0, 0, 1, 1);

// Start periodic data fetching: get latest data point every 7.5 seconds
// This keeps the graph current with new data
timerget = setInterval(getdp, 7500);

// Start GPU-friendly rendering loop
gpu_fast();
// Alternative (older method): setInterval(fast,150);  // 150ms = ~6.7 FPS

/**
 * GPU-friendly rendering loop using requestAnimationFrame.
 * 
 * This function creates a smooth, efficient rendering loop that:
 * - Uses requestAnimationFrame for GPU acceleration
 * - Respects browser refresh rate for smooth animation
 * - Updates at configurable FPS (fast_update_fps: 10-20 FPS)
 * - Automatically pauses when tab is hidden (browser optimization)
 * 
 * How it works:
 * 1. Schedules next frame using requestAnimationFrame
 * 2. Calls fast() to update time window and render
 * 3. Waits (1000/fps) milliseconds before next iteration
 * 
 * Performance:
 * - More efficient than setInterval() because it syncs with browser repaint
 * - Automatically throttles when tab is not visible
 * - Adjusts FPS based on time window (see viewWindow click handler)
 * 
 * @see fast_update_fps - Global variable controlling update rate (10-20 FPS)
 */
function gpu_fast() {
    setTimeout(
        function() {
            // Schedule next frame using browser's animation frame
            // This ensures smooth rendering and GPU acceleration
            window.requestAnimationFrame(gpu_fast);
            fast();  // Update and render
        }, 1000 / fast_update_fps);  // Wait based on target FPS
};

/**
 * Updates time window to current time and re-renders graph.
 * 
 * This function is called repeatedly by gpu_fast() to keep the graph
 * showing the most recent time window. It shifts the time window forward
 * to always end at "now", creating a scrolling effect.
 * 
 * The time window size (timeWindow) remains constant, but the end time
 * continuously updates to current time.
 */
function fast() {
    var now = (new Date()).getTime();
    start = now - timeWindow;  // Start time: timeWindow ago
    end = now;                  // End time: current time (always updating)
    plot();                     // Re-render with updated time window
}

$(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse', vis_resize);

function vis_resize() {
    graph.width(graph_bound.width());
    if (embed) graph.height($(window).height());
    window.requestAnimationFrame(plot);
}

/**
 * Fetches the latest data point from the feed asynchronously.
 * 
 * This function is called periodically (every 7.5 seconds by default, or
 * dynamically based on time window) to get the most recent value from the feed.
 * 
 * AJAX Pattern:
 * - Uses jQuery $.ajax() for async HTTP GET request
 * - Endpoint: /feed/timevalue.json
 * - Returns: {time: timestamp_seconds, value: number}
 * 
 * Data Management:
 * - Adds new point only if timestamp is different from last point (avoids duplicates)
 * - Removes old points outside current time window (prevents memory growth)
 * - Sorts data array to maintain chronological order
 * 
 * Error Handling:
 * - No explicit error callback (silent failure)
 * - If AJAX fails, data simply won't update (graph continues with existing data)
 * - Consider adding .fail() handler for production use
 * 
 * @see timerget - Interval ID, cleared and reset when time window changes
 */
function getdp() {
    $.ajax({
        url: path + "feed/timevalue.json",  // Feed API endpoint for latest value
        data: "id=" + feedid,                // Query parameter: feed ID
        dataType: 'json',                    // Expect JSON response
        async: true,                          // Asynchronous request (non-blocking)
        success: function(result) {
            // result format: {time: timestamp_seconds, value: number}
            
            if (data.length == 0) {
                // No data yet: add first point
                // Convert timestamp from seconds to milliseconds
                data.push([result.time * 1000, parseFloat(result.value)]);
            }
            else if (data.length>0) {
                // Check if this is a new data point (different timestamp)
                // Prevents adding duplicate points if called multiple times
                if (data[data.length - 1][0] != result.time * 1000) {
                    // New point: add to data array
                    data.push([result.time * 1000, parseFloat(result.value)]);
                }
                
                // Remove old data points outside current time window
                // This prevents memory from growing indefinitely
                // Check second point (index 1) because we want to keep at least one point
                if (data[1] && data[1][0] < (start)) {
                    data.splice(0, 1);  // Remove first point if it's outside window
                }
                
                // Sort data by timestamp to maintain chronological order
                // Important if points arrive out of order
                data.sort();
            }
        }
        // Note: No error handler - failures are silent
        // Consider adding: .fail(function() { console.error("Failed to fetch latest data"); });
    });
}

/**
 * Renders the graph using Flot.js plotting library.
 * 
 * This function is called frequently by the rendering loop to update the display.
 * It handles unit conversion (W to kW) if needed, then renders the graph.
 * 
 * Unit Conversion:
 * - If is_kw is true, converts watts to kilowatts (divide by 1000)
 * - Uses data[n][2] as a flag to prevent re-converting already converted values
 * - Conversion happens on-the-fly during rendering
 * 
 * @see data - Global array of [timestamp, value] pairs
 * @see is_kw - Global flag: true = convert W to kW
 * @see plotColour - Global color variable
 */
function plot() {
    // Convert watts to kilowatts if needed (is_kw flag)
    if (is_kw) {
        var word = 'converted';  // Flag to mark converted values
        for (n in data) {
            // Check if value exists, hasn't been converted, and is valid
            if (data[n] && data[n][1] && typeof data[n][2] === 'undefined' && typeof data[n][2] !== word) {
                data[n][1] = data[n][1] / 1000;  // Convert W to kW
                data[n][2] = word;                // Mark as converted
            }
        }
    }
    
    // Render graph with Flot.js
    $.plot(graph, [{
        data: data,              // Data array
        color: plotColour        // Line color
    }], {
        canvas: true,             // Use HTML5 canvas for rendering
        lines: {
            fill: true            // Fill area under line
        },
        series: {
            shadowSize: 0         // No shadow for performance
        },
        xaxis: {
            tickLength: 10,       // Tick mark length
            mode: "time",         // X-axis displays time
            timezone: "browser",  // Use browser's timezone
            min: start,           // Minimum time (sliding window start)
            max: end              // Maximum time (always current time)
        },
        touch: {
            pan: "x",             // Allow panning on touch devices (x-axis)
            scale: "x"            // Allow pinch-to-zoom (x-axis)
        }
    });
}

/**
 * Time window selection button handler.
 * 
 * When user clicks a time window button (1 min, 5 min, 15 min, etc.),
 * this function:
 * 1. Updates the time window size
 * 2. Adjusts refresh rate based on window size (longer = slower updates)
 * 3. Adjusts rendering FPS (longer = lower FPS for performance)
 * 4. Fetches new historical data for the window
 * 
 * Dynamic Refresh Rate:
 * - Short windows (< 5 min): Fast updates (20 FPS, frequent data fetches)
 * - Long windows (> 5 min): Slower updates (10 FPS, less frequent fetches)
 * - Minimum rate: 1.8 seconds (prevents excessive server load)
 * 
 * Performance Optimization:
 * - Longer time windows = lower FPS = better performance
 * - Refresh rate scales with window size to balance detail vs. load
 */
$('.viewWindow').click(function() {
    // Get time window from button's "time" attribute (in seconds)
    timeWindow = (1000 * $(this).attr("time"));  // Convert to milliseconds
    start = end - timeWindow;  // Calculate start time

    // Calculate optimal refresh rate based on time window size
    var rate = 0;
    if (timeWindow > 300 * 1000) {  // Window > 5 minutes
        // Longer windows: slower updates for performance
        rate = timeWindow / 120;     // Refresh every (window/120) milliseconds
        fast_update_fps = 10;        // Lower FPS for longer windows
    } else {
        // Shorter windows: faster updates for detail
        rate = timeWindow / 60;      // Refresh every (window/60) milliseconds
        fast_update_fps = 20;        // Higher FPS for shorter windows
    }
    
    // Limit maximum refresh rate (minimum 1.8 seconds between fetches)
    // This prevents excessive server load
    if (rate < 1800) rate = 1800;
    
    // Update periodic data fetching interval
    clearInterval(timerget);  // Clear old interval
    timerget = setInterval(getdp, rate);  // Set new interval with calculated rate
    
    // Debug log (can be removed in production)
    console.log("realtime timewindow " + timeWindow / 1000 + "s get rate " + rate / 1000 + "s");

    // Recalculate data interval for historical data fetch
    interval = parseInt(((end * 0.001 + 10) - (start * 0.001 - 10)) / 800);
    if (interval<1) interval = 1;  // Minimum interval: 1 second
    
    /**
     * Fetch new historical data for the updated time window
     * 
     * This is a SYNCHRONOUS call that blocks until data is received.
     * Parameters are same as initial load, but with updated time window.
     */
    data = feed.getdata(feedid, (start - 10000), (end + 10000), interval, 0, 0, 1, 1);
});
</script>
