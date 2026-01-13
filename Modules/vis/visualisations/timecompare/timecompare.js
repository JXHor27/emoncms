/**
 * Time Comparison Visualization JavaScript
 * 
 * This file handles rendering graphs that compare the same feed across different time periods.
 * For example, comparing today's power consumption with yesterday's, or this week vs last week.
 * 
 * Data Flow:
 * 1. User interaction triggers vis_feed_data()
 * 2. vis_feed_data() debounces and calls vis_feed_data_delayed()
 * 3. vis_feed_data_delayed() creates plotlist with time-shifted periods
 * 4. For each period, initiates async AJAX via feed.getdata() with adjusted time range
 * 5. On completion, vis_feed_data_callback() adjusts timestamps to overlay on current graph
 * 6. plot() renders all periods together for comparison
 * 
 * Key Concept - Time Shifting:
 * - Each comparison period fetches data from a past time window
 * - Timestamps are then shifted forward to overlay on the current time window
 * - This allows visual comparison: "Today vs Yesterday" shows both on same graph
 * 
 * Error Handling:
 * - Failed AJAX: Callback receives null/undefined data, feed won't be plotted
 * - Aborted requests: ajaxAsyncXdr tracks XHR objects for abort()
 * - Missing periods: Gracefully degrades - available periods still display
 */

var plotdata = [];              // Array of plot series for Flot.js rendering
var event_vis_feed_data;        // Timeout ID for debouncing feed data requests
var ajaxAsyncXdr = [];          // Array of XMLHttpRequest objects for aborting pending requests
var compare_unit = 0;           // Time unit for comparison (milliseconds: day, week, month, year)
var xaxis_format = "";          // Format string for X-axis time labels (set based on compare_unit)
  
/**
 * Creates plot series configuration for time comparison visualization.
 * 
 * Generates multiple plot series, one for each comparison period (current, 1 period ago, 2 periods ago, etc.).
 * Each series represents the same feed but from a different time period, shifted to overlay on the graph.
 * 
 * @param {number} feedid - Feed ID to compare across time periods
 * @param {boolean} fill - Whether to fill area under the line
 * @param {number} depth - Number of comparison periods to show (e.g., 3 = current + 2 prior periods)
 * 
 * @returns {Array} Array of plot series objects, one for each comparison period
 *   Each object contains:
 *   - id: Feed ID
 *   - selected: Boolean (1=visible)
 *   - depth: Period offset (0=current, 1=1 period ago, etc.)
 *   - plot: Flot.js series config with adj property for timestamp adjustment
 */
function create_plotlist(feedid, fill, depth) {
  var plotlist = [];
  var unit = "";
  var unit_plural = "s";

  // Determine time unit and X-axis format based on compare_unit duration
  // This affects both labels and how timestamps are displayed
  switch(compare_unit) {
    case 1000*60*60*24:              // 1 day
      unit = "Day";
      xaxis_format = "%H:%M";         // Show hours:minutes
      break;
    case 1000*60*60*24*7:             // 1 week
      unit = "Week";
      xaxis_format = "%a<br>%H:%M";  // Show day of week + time
      break;
    case 1000*60*60*24*28:            // ~1 month (28 days)
      unit = "Month";
      xaxis_format = "%a<br>%H:%M";  // Show day of week + time
      break;
    case 1000*60*60*24*365:          // 1 year
      unit = "Year";
      xaxis_format = "%m/%d<br>%a<br>%H:%M"; // Show month/day + day of week + time
      break;
    default:
      // Custom time unit (fallback)
      unit = compare_unit / 1000 + " Seconds";
      unit_plural = "";
      xaxis_format = "%H:%M";
  }

  for (var i = 0; i < depth; i++) {
    var cur_depth = depth - i - 1;
    var label;

    if (0 == cur_depth) {
      label = "Current";
    } else if (1 == cur_depth) {
      label = cur_depth + " " + unit + " Prior";
    } else {
      label = cur_depth + " " + unit + unit_plural + " Prior";
    }

    // Create plot series for this comparison period
    plotlist[i] = {
      id: feedid,
      selected: 1,
      depth: cur_depth,              // Period offset: 0=current, 1=1 period ago, etc.
      plot: {
        adj: compare_unit * cur_depth, // Timestamp adjustment: shifts old data forward to overlay
                                      // Example: If comparing days, yesterday's data is shifted +24h
        data: null,                   // Will be populated by AJAX callback
        label: label,                 // Display label (e.g., "Current", "1 Day Prior")
        yaxis: 1,                     // Use left Y-axis
        lines: {
          show: true,
          fill: fill                  // Fill area under line if enabled
        }
      }
    };
  }

  return plotlist;
}

/**
 * Main entry point for fetching feed data for time comparison.
 * 
 * Implements debouncing (500ms delay) to prevent excessive AJAX calls when user
 * rapidly changes the time window (zooming, panning).
 * 
 * Call Chain: vis_feed_data() → vis_feed_data_delayed() → feed.getdata() → vis_feed_data_callback()
 */
function vis_feed_data() {
  // Debounce: Cancel any pending delayed request and schedule a new one
  // This prevents multiple AJAX calls when user rapidly changes view
  clearTimeout(event_vis_feed_data);
  event_vis_feed_data = setTimeout(function() { vis_feed_data_delayed(); }, 500);
  
  // Immediately render with existing data (may be stale, but provides instant feedback)
  plot();
}
  
/**
 * Initiates asynchronous AJAX requests to fetch feed data for each comparison period.
 * 
 * This function handles the time-shifting logic:
 * - For each comparison period (current, 1 ago, 2 ago, etc.)
 * - Calculates the historical time range for that period
 * - Fetches data from that historical range
 * - Callback will shift timestamps forward to overlay on current graph
 * 
 * Time Shifting Example (comparing days):
 * - Current period: Fetch data from today (view.start to view.end)
 * - 1 Day Prior: Fetch data from yesterday (view.start - 1day to view.end - 1day)
 * - Callback shifts yesterday's timestamps forward by 1 day to overlay on today's graph
 * 
 * AJAX Pattern:
 * - Uses feed.getdata() for async HTTP requests
 * - Stores XHR objects in ajaxAsyncXdr[] for abort capability
 * - Each period gets its own AJAX request (may have multiple pending)
 * 
 * Error Handling:
 * - Aborts pending requests before starting new ones
 * - Callback validates data before using it
 * - Failed periods simply won't display (graceful degradation)
 */
function vis_feed_data_delayed() {
  var plotlist;
  fill = fill > 0 ? true : false;
  if (depth <= 0) depth = 3;
  if (npoints <= 0) npoints = 2400;
	
  // Create plot series for each comparison period
  plotlist = create_plotlist(feedid, fill, depth);
  
  // Iterate through each comparison period
  for(var i in plotlist) {
    if (plotlist[i].selected) {
      // Only fetch if we don't already have data for this period
      if (!plotlist[i].plot.data) {
        var skipmissing = false;
        
        // Calculate historical time range for this comparison period
        // Shift the time window backward by (depth * compare_unit)
        // Example: If comparing days and depth=1, fetch yesterday's data
        var plot_start = view.start - (compare_unit * plotlist[i].depth);
        var plot_end = view.end - (compare_unit * plotlist[i].depth);
        
        // Note: This doesn't account for leap years, which may cause slight misalignment
        // for year-over-year comparisons
        
        // Calculate optimal data interval: aim for ~2400 data points
        interval = Math.round((view.end - view.start)/(npoints * 1000));

        // Initialize plotdata array slot if needed
        if (plotdata[i] === undefined) {
          plotdata[i] = [];
        }

        // Abort any pending AJAX request for this period to prevent race conditions
        if (typeof ajaxAsyncXdr[i] !== 'undefined') { 
          ajaxAsyncXdr[i].abort(); // Abort pending loads
          ajaxAsyncXdr[i] = undefined;
        }
        
        // Create context object to pass to callback
        var context = {index:i, plotlist:plotlist[i]};
        
        /**
         * Initiate asynchronous AJAX request to fetch feed data for this comparison period
         * 
         * Parameters:
         * @param {number} feedid - Feed ID to fetch
         * @param {number} plot_start - Historical start time (milliseconds, shifted backward)
         * @param {number} plot_end - Historical end time (milliseconds, shifted backward)
         * @param {number} interval - Data interval in seconds
         * @param {number} 0 - Average mode: disabled
         * @param {number} 0 - Delta mode: disabled
         * @param {boolean} skipmissing - Skip missing data points
         * @param {number} 1 - Limit interval mode: enabled
         * @param {function} vis_feed_data_callback - Callback function
         * @param {object} context - Context object with period information
         * 
         * Returns: XMLHttpRequest object (stored in ajaxAsyncXdr[] for abort capability)
         */
        ajaxAsyncXdr[i] = feed.getdata(
          plotlist[i].id,
          plot_start,
          plot_end,
          interval,
          0,                          // Average: off
          0,                          // Delta: off
          skipmissing,
          1,                          // Limit interval: on
          vis_feed_data_callback,
          context
        );
      }
    }
  }
}
  
/**
 * Callback function called when AJAX feed data request completes.
 * 
 * This function performs the critical time-shifting operation:
 * - Receives historical data (e.g., yesterday's power consumption)
 * - Shifts all timestamps forward by (compare_unit * depth)
 * - This allows historical data to overlay on the current time window graph
 * 
 * Example: Comparing days (compare_unit = 24 hours, depth = 1)
 * - Fetched data: Yesterday 10:00 AM → value 1000W
 * - After shift: Today 10:00 AM → value 1000W (overlays on today's graph)
 * 
 * Error Handling:
 * - If data is null/undefined (request failed), the period won't be plotted
 * - Check data validity before assigning to prevent errors
 * - Failed periods gracefully degrade - other periods still display
 * 
 * @param {object} context - Context object passed from vis_feed_data_delayed()
 *   - context.index: Period index in plotlist
 *   - context.plotlist: Plot series configuration with depth property
 * @param {Array|null|undefined} data - Feed data array or null/undefined on error
 *   Format: [[timestamp1, value1], [timestamp2, value2], ...]
 *   Timestamps are in milliseconds (historical time)
 *   Values are numbers or null for missing data points
 */
function vis_feed_data_callback(context, data) {
  var i = context['index'];
  var depth = context['plotlist'].depth;

  // Time-shift operation: Adjust all timestamps forward to overlay on current graph
  // This is the key to time comparison visualization
  for (var d in data) {
    // Shift timestamp forward by (compare_unit * depth)
    // Example: If comparing days (compare_unit = 24h) and depth=1 (yesterday),
    // shift yesterday's timestamps forward by 24 hours to overlay on today
    data[d][0] = data[d][0] + (compare_unit * depth);
  }

  // Store shifted data in plot series configuration
  context['plotlist'].plot.data = data;
  
  // Only update plotdata if we received valid data
  // This prevents errors when AJAX request fails (data will be null/undefined)
  if (context['plotlist'].plot.data) {
    plotdata[i] = context['plotlist'].plot;
  }
  // Note: If data is invalid, this period simply won't be plotted
  // This is graceful degradation - other periods will still display

  // Re-render graph with updated data
  // Called for each period as its data arrives (may cause multiple renders)
  // Flot.js handles this efficiently
  plot();
}

/**
 * Renders the time comparison graph using Flot.js.
 * 
 * Displays multiple time periods overlaid on the same graph for visual comparison.
 * Each period is a different series, allowing users to compare current vs. historical data.
 * 
 * @see plotdata - Global array of plot series, updated by vis_feed_data_callback()
 * @see xaxis_format - Time format string set based on compare_unit
 */
function plot() {
  $.plot($("#graph"), plotdata, {
    canvas: true,                    // Use HTML5 canvas for rendering (better performance)
    grid: { 
      show: true,                    // Show grid lines
      hoverable: true,               // Enable hover tooltips
      clickable: true                // Enable click selection
    },
    xaxis: { 
      mode: "time",                  // X-axis displays time
      timezone: "browser",           // Use browser's timezone
      timeformat: xaxis_format,      // Format based on compare_unit (e.g., "%H:%M" for days)
      min: view.start,               // Minimum time (ms) - current time window
      max: view.end                 // Maximum time (ms) - current time window
    },
    selection: { mode: "x" },        // Allow selecting time range by dragging
    legend: { 
      position: "nw",                // Position: northwest
      toggle: true                   // Allow clicking legend items to show/hide series
    },
    toggle: { scale: "visible" },     // Toggle series visibility
    touch: { 
      pan: "x",                      // Allow panning on touch devices (x-axis only)
      scale: "x"                     // Allow pinch-to-zoom (x-axis only)
    }
  });
}

function timecompare_init(element) {
  // Get start and end time of view based on default scale and current time
  var now = new Date().getTime();

  plotdata = [];
  compare_unit = (1000*60*60*initzoom);
  view.start = now - compare_unit;
  view.end = now;

  var out =
    "<div id='graph_bound' style='height:400px; width:100%; position:relative; '>"+
      "<div id='graph'></div>"+
      "<div id='graph-buttons' style='position:absolute; top:20px; right:30px; opacity:0.5; display: none;'>"+
        "<div class='input-prepend input-append' id='graph-tooltip' style='margin:0'>"+
        "<span class='add-on'>Tooltip:</span>"+
        "<span class='add-on'><input id='enableTooltip' type='checkbox' checked ></span>"+
        "</div> "+

        "<div class='btn-group'>"+
        "<button class='btn graph-time' type='button' time='1'>D</button>"+
        "<button class='btn graph-time' type='button' time='7'>W</button>"+
        "<button class='btn graph-time' type='button' time='28'>M</button>"+
        "<button class='btn graph-time' type='button' time='365'>Y</button></div>"+

        "<div class='btn-group' id='graph-navbar' style='display: none;'>"+
        "<button class='btn graph-nav' id='zoomin'>+</button>"+
        "<button class='btn graph-nav' id='zoomout'>-</button>"+
        "<button class='btn graph-nav' id='left'><</button>"+
        "<button class='btn graph-nav' id='right'>></button></div>"+

        "<div class='btn-group'>"+
         "<button class='btn graph-exp' id='graph-fullscreen' type='button'><i class='icon-resize-full'></i></button></div>"+
      "</div>"+
    "</div>"
  ;
  $(element).html(out);

  // Tool tip
  var previousPoint = null;
  $(element).bind("plothover", function (event, pos, item) {
    //$("#x").text(pos.x.toFixed(2));
    //$("#y").text(pos.y.toFixed(2));

    if ($("#enableTooltip:checked").length > 0) {
      if (item) {
        if (previousPoint != item.dataIndex) {
          previousPoint = item.dataIndex;

          $("#tooltip").remove();
          var x = item.datapoint[0].toFixed(2);
          var y = item.datapoint[1].toFixed(2);

          var pointDate = new Date(parseInt(x) - parseInt(item.series.adj));
          var tipText = $.plot.formatDate(pointDate, y + "<br>%a %b %d %Y<br>%H:%M:%S");

          tooltip(item.pageX, item.pageY, tipText, "#DDDDDD");
        }
      } else {
        $("#tooltip").remove();
        previousPoint = null;
      }
    }
  });

  var backgroundColour; //= urlParams.colourbg;
  if (backgroundColour==undefined || backgroundColour=='') backgroundColour = "ffffff";
  $("body").css("background-color","#"+backgroundColour);
  document.body.style.setProperty("--bg-vis-graph-color", "#"+backgroundColour);

  $('#graph').width($('#graph_bound').width());
  $('#graph').height($('#graph_bound').height());
  if (embed) $('#graph').height($(window).height());

  $(function() {
    $(document).on('window.resized hidden.sidebar.collapse shown.sidebar.collapse', vis_resize);
  })

  function vis_resize() {
    $('#graph').width($('#graph_bound').width());
    if (embed) $('#graph').height($(window).height());
    plot();
  }

  // Graph selections
  $("#graph").bind("plotselected", function (event, ranges) {
     view.start = ranges.xaxis.from; 
     view.end = ranges.xaxis.to;
     vis_feed_data();
  });

  // Navigation actions
  $("#zoomout").click(function () {view.zoomout(); vis_feed_data();});
  $("#zoomin").click(function () {view.zoomin(); vis_feed_data();});
  $('#right').click(function () {view.panright(); vis_feed_data();});
  $('#left').click(function () {view.panleft(); vis_feed_data();});
  $("#graph-fullscreen").click(function () {view.fullscreen();});
  $('.graph-time').click(function () {
    view.timewindow($(this).attr("time"));
    compare_unit = view.end - view.start;
    vis_feed_data();
  });

  // Navigation and zooming buttons for mouse and touch
  $("#graph").mouseenter(function() {
    $("#graph-navbar").show();
    $("#graph-tooltip").show();
    $("#graph-buttons").stop().fadeIn();
    $("#stats").stop().fadeIn();
  });
  $("#graph_bound").mouseleave(function() {
    $("#graph-buttons").stop().fadeOut();
    $("#stats").stop().fadeOut();
  });
  $("#graph").bind("touchstarted", function (event, pos) {
    $("#graph-navbar").hide();
    $("#graph-tooltip").hide();
    $("#graph-buttons").stop().fadeOut();
    $("#stats").stop().fadeOut();
  });
  $("#graph").bind("touchended", function (event, ranges) {
    $("#graph-buttons").stop().fadeIn();
    $("#stats").stop().fadeIn();
    view.start = ranges.xaxis.from; 
    view.end = ranges.xaxis.to;
    vis_feed_data();
  });
}
