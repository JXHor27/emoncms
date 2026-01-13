/**
 * Multigraph Visualization JavaScript
 * 
 * This file handles the rendering and data fetching for multigraph visualizations.
 * A multigraph displays multiple feeds together in a single interactive graph.
 * 
 * Data Flow:
 * 1. User interaction triggers visFeedData()
 * 2. visFeedData() handles autorefresh logic and calls visFeedDataOri()
 * 3. visFeedDataOri() debounces requests (500ms delay) and calls visFeedDataDelayed()
 * 4. visFeedDataDelayed() initiates async AJAX calls via feed.getdata() for each feed
 * 5. feed.getdata() makes HTTP request to Feed API
 * 6. On success, visFeedDataCallback() is called with the data
 * 7. visFeedDataCallback() updates plotdata array and triggers plot() to render graph
 * 
 * Error Handling:
 * - Failed AJAX requests: feed.getdata() callback receives null/undefined data
 * - Aborted requests: ajaxAsyncXdr array tracks XHR objects for abort() calls
 * - Missing data: Check for data validity in callback before plotting
 * 
 * Dependencies:
 * - feed.getdata() from Modules/feed/feed.js - handles AJAX requests to Feed API
 * - view object from Lib/vis.helper.js - manages time window and view state
 * - tooltip() from Lib/vis.helper.js - displays hover tooltips
 * - parse_timepicker_time() from Lib/vis.helper.js - parses date strings
 * - $.plot() from Flot.js - renders the graph
 */

//get_feed_data_async is defined in /Modules/vis/visualisations/common/api.js
/*global get_feed_data_async */
//view, tooltip and parse_timepicker_time are defined in Lib/vis.helper.js
/*global tooltip */
/*global parse_timepicker_time */
/*global view */
//multigraphFeedlist and embed are defined in the script part of multigraph.php
/*global multigraphFeedlist */
/*global embed*/
/*eslint no-undef: "error"*/
/*eslint no-console: ["error", { allow: ["warn", "error"] }] */

/**
 * Global Variables
 */
var plotdata = [];              // Array of plot series data for Flot.js rendering
var timeWindowChanged = 0;      // Flag tracking if time window has changed (currently unused)
var ajaxAsyncXdr = [];          // Array of XMLHttpRequest objects, used to abort pending AJAX requests
                                // Index corresponds to feed index in multigraphFeedlist
var eventVisFeedData;           // Timeout ID for debouncing feed data requests
var eventRefresh;               // Timeout ID for autorefresh interval
var showlegend = true;          // Whether to display the graph legend
var backgroundColour = "ffffff"; // Background color for the visualization (hex, no #)
var datetimepicker1;            // jQuery datetimepicker instance for start time
var datetimepicker2;            // jQuery datetimepicker instance for end time
var graphtype;                  // Current graph type ("lines", "bars", "lineswithsteps")
var intervaltype;               // Data interval type for feed queries

/**
 * Converts multigraph feed list configuration into Flot.js plot series format.
 * 
 * This function processes the multigraphFeedlist array and creates plot series
 * configurations for each feed, including styling, colors, graph types, and axis assignments.
 * 
 * @param {Array} multigraphFeedlist - Array of feed configuration objects
 *   Each object contains:
 *   - id: Feed ID number
 *   - name: Feed name string
 *   - tag: Optional tag prefix for label
 *   - graphtype: "lines", "bars", or "lineswithsteps"
 *   - lineColour: Color hex code (with or without #)
 *   - fill: Boolean for area fill
 *   - stacked: Boolean for stacked display
 *   - left/right: Boolean for y-axis assignment (left=1, right=2)
 *   - showtag: Boolean to show tag in label
 *   - showlegend: Boolean to show legend
 *   - ymin/ymax/y2min/y2max: Optional axis limits
 *   - backgroundColour: Background color hex
 *   - barwidth: Bar width multiplier (for bar graphs)
 *   - skipmissing: Skip missing data points
 *   - delta: Calculate delta values
 *   - average: Average data points
 *   - intervaltype: Data interval type
 * 
 * @returns {Array|undefined} Array of plot series objects for Flot.js, or undefined if invalid input
 *   Each plot series object contains:
 *   - id: Feed ID
 *   - selected: Boolean (1=visible, 0=hidden)
 *   - plot: Flot.js series configuration object
 */
function convertToPlotlist(multigraphFeedlist) {
  // Validate input: check if multigraphFeedlist exists and has at least one item
  if (multigraphFeedlist==undefined) return;
  if (!multigraphFeedlist[0]) return;
  var plotlist = [];

  var showtag = (typeof multigraphFeedlist[0]["showtag"] !== "undefined" ? multigraphFeedlist[0]["showtag"] : true);
  showlegend = (typeof multigraphFeedlist[0]["showlegend"] === "undefined" || multigraphFeedlist[0]["showlegend"]);
  var barwidth = 1;

  view.ymin = (typeof multigraphFeedlist[0]["ymin"] !== "undefined" ? multigraphFeedlist[0]["ymin"] : null);
  view.ymax = (typeof multigraphFeedlist[0]["ymax"] !== "undefined" ? multigraphFeedlist[0]["ymax"] : null);
  view.y2min = (typeof multigraphFeedlist[0]["y2min"] !== "undefined" ? multigraphFeedlist[0]["y2min"] : null);
  view.y2max = (typeof multigraphFeedlist[0]["y2max"] !== "undefined" ? multigraphFeedlist[0]["y2max"] : null);

  backgroundColour = (typeof multigraphFeedlist[0]["backgroundColour"] !== "undefined" ? multigraphFeedlist[0]["backgroundColour"] : "ffffff");
  $("body").css("background-color","#"+backgroundColour);
  document.body.style.setProperty("--bg-vis-graph-color", "#"+backgroundColour);
  
  for (var z in multigraphFeedlist) {
    var currentFeed=multigraphFeedlist[parseInt(z,10)];
    var tag = (showtag && typeof currentFeed["tag"] !== "undefined" && currentFeed["tag"] !== "" ? currentFeed["tag"]+": " : "");
    var stacked = (typeof currentFeed["stacked"] !== "undefined" && currentFeed["stacked"]);
    barwidth = typeof currentFeed["barwidth"] === "undefined" ? 1 : currentFeed["barwidth"];

    if ( typeof currentFeed["graphtype"] === "undefined" ) {
      graphtype="lines"; // graphtype="bars"
    } else {
      graphtype=currentFeed["graphtype"];
    }

    if (graphtype.substring(0, 5) === "lines") {
      plotlist[parseInt(z,10)] = {
        id: currentFeed["id"],
        selected: 1,
        plot: {
          data: null,
          label: tag + currentFeed["name"],
          stack: stacked,
          points: {
            show: true,
            radius: 0,
            lineWidth: 1, // in pixels
            fill: false
          },
          lines: {
            show: true,
            fill: currentFeed["fill"] ? (stacked ? 1.0 : 0.5) : 0.0,
            steps: graphtype === "lineswithsteps" ? true : false
          }
        }
      };
    }

    else if (graphtype === "bars") {
      plotlist[parseInt(z,10)] = {
        id: currentFeed["id"],
        selected: 1,
        plot: {
          data: null,
          label: tag + currentFeed["name"],
          stack: stacked,
          bars: {
            show: true,
            align: "center", barWidth: 3600*24*1000*barwidth, fill: currentFeed["fill"] ? (stacked ? 1.0 : 0.5) : 0.0
          }
        }
      };
    } else {
      // custom console
      console.error("ERROR: Unknown plot graphtype! Graphtype: ", currentFeed["graphtype"]);
    }

    if (currentFeed["left"] === true) {
      plotlist[parseInt(z,10)].plot.yaxis = 1;
    } else if (currentFeed["right"] === true) {
      plotlist[parseInt(z,10)].plot.yaxis = 2;
    } else {
      // custom console
      console.error("ERROR: Unknown plot alignment! Alignment setting: ", currentFeed["right"]);
    }

    // Only set the plotcolour variable if we have a value to set it with
    if (currentFeed["lineColour"]) {
      // Some browsers really want the leading "#". It works without in chrome, not in IE and opera.
      // What the hell, people?
      if (currentFeed["lineColour"].indexOf("#") === -1) {
        plotlist[parseInt(z,10)].plot.color = "#" + currentFeed["lineColour"];
      } else {
        plotlist[parseInt(z,10)].plot.color = currentFeed["lineColour"];
      }
    }

    if (currentFeed["left"] === false && currentFeed["right"] === false) {
      plotlist[parseInt(z,10)].selected = 0;
    }
  }
  return plotlist;
}



/**
 * Main entry point for fetching feed data.
 * 
 * Handles autorefresh logic: if autorefresh is enabled and the current time window
 * is close to "now" (within 2x autorefresh interval), it automatically updates
 * the end time to current time and schedules the next refresh.
 * 
 * Call Chain: visFeedData() → visFeedDataOri() → visFeedDataDelayed() → feed.getdata() → visFeedDataCallback()
 * 
 * @param {number} autorefresh - Optional autorefresh interval in seconds (from multigraphFeedlist[0].autorefresh)
 *   If set, automatically refreshes data when viewing recent time windows
 */
function visFeedData() {
    // Check if autorefresh is configured for this multigraph
    if (typeof multigraphFeedlist !== "undefined" && typeof multigraphFeedlist[0] !== "undefined" && typeof multigraphFeedlist[0]["autorefresh"] !== "undefined") {
        var now = new Date().getTime();
        var timeWindow = view.end - view.start;
        
        // If viewing recent data (within 2x autorefresh interval of now), enable auto-update
        // This keeps the graph current when viewing live/recent data
        if (now - view.end < 2000 * multigraphFeedlist[0]["autorefresh"]) {
            // Update time window to current time while maintaining window size
            view.end = now;
            view.start = view.end - timeWindow;
            visFeedDataOri();
            
            // Schedule next autorefresh: clear any pending refresh and set new one
            clearTimeout(eventRefresh);
            eventRefresh = setTimeout(visFeedData, 1000 * multigraphFeedlist[0]["autorefresh"]);
        } else {
            // Not viewing recent data, just fetch once
            visFeedDataOri();
        }
    } else {
        // No autorefresh configured, just fetch data once
        visFeedDataOri();
    }
}


/**
 * Debounces feed data requests to prevent excessive AJAX calls.
 * 
 * This function implements a 500ms debounce delay to avoid making multiple
 * requests when the user rapidly changes the time window (e.g., zooming, panning).
 * 
 * Also updates the datetime picker UI to reflect current view time window.
 * 
 * @see visFeedDataDelayed() - Called after debounce delay
 */
function visFeedDataOri() {
  // Update datetime picker UI to match current view time window
  datetimepicker1.setLocalDate(new Date(view.start));
  datetimepicker2.setLocalDate(new Date(view.end));
  datetimepicker1.setEndDate(new Date(view.end));
  datetimepicker2.setStartDate(new Date(view.start));

  // Debounce: Cancel any pending delayed request and schedule a new one
  // This prevents multiple AJAX calls when user rapidly changes view
  clearTimeout(eventVisFeedData);
  eventVisFeedData = setTimeout(function() { visFeedDataDelayed(); }, 500);
  
  // If feed list length changed, reset plotdata array to match
  if (typeof multigraphFeedlist !== "undefined" && multigraphFeedlist.length !== plotdata.length) {
    plotdata = [];
  }
  
  // Immediately render with existing data (may be stale, but provides instant feedback)
  plot();
}



/**
 * Initiates asynchronous AJAX requests to fetch feed data for all selected feeds.
 * 
 * This function is called after the debounce delay. It:
 * 1. Converts feed list to plot series format
 * 2. Calculates optimal data interval based on time window
 * 3. For each selected feed without data, initiates an async AJAX request
 * 4. Aborts any pending requests for the same feed to prevent race conditions
 * 
 * AJAX Pattern:
 * - Uses feed.getdata() which makes HTTP GET request to Feed API
 * - Returns XMLHttpRequest object stored in ajaxAsyncXdr[] for potential abort()
 * - On success, calls visFeedDataCallback() with data
 * - On error, callback receives null/undefined data (check in callback)
 * 
 * Error Handling:
 * - Aborts pending requests before starting new ones (prevents race conditions)
 * - Callback should validate data before using it
 * - Network errors are handled by feed.getdata() internally
 * 
 * @see visFeedDataCallback() - Called when AJAX request completes
 * @see feed.getdata() - Defined in Modules/feed/feed.js, handles HTTP requests
 */
function visFeedDataDelayed() {
  // Convert feed configuration to Flot.js plot series format
  var plotlist = convertToPlotlist(multigraphFeedlist);
  
  // Calculate optimal data interval: aim for ~2400 data points
  // This balances detail vs. performance
  var npoints = 2400;
  var interval = Math.round(((view.end - view.start)/npoints)/1000); // Convert to seconds

  // Iterate through each feed in the plot list
  for(var i in plotlist) {
    // Only fetch data for selected (visible) feeds
    if (plotlist[parseInt(i,10)].selected) {
      // Only fetch if we don't already have data for this feed
      if (!plotlist[parseInt(i,10)].plot.data) {
        // Configure data processing options from feed settings
        var skipmissing = 0;
        if (multigraphFeedlist[parseInt(i,10)]["skipmissing"]) skipmissing = 1;
        
        var delta = 0;
        // Delta mode calculates difference between consecutive points
        // When delta is enabled, skipmissing is automatically disabled
        if (multigraphFeedlist[parseInt(i,10)]["delta"]!=undefined && multigraphFeedlist[parseInt(i,10)]["delta"]) {
            delta = 1;
            skipmissing = 0;
        }
        
        var average = 0;
        // Average mode calculates mean values over the interval
        if (multigraphFeedlist[parseInt(i,10)]["average"]!=undefined && multigraphFeedlist[parseInt(i,10)]["average"]) {
            average = 1;
        }
        
        // Override calculated interval if feed has specific intervaltype setting
        if (multigraphFeedlist[parseInt(i,10)]["intervaltype"]!=undefined && multigraphFeedlist[parseInt(i,10)]["intervaltype"]!="standard") {
            interval = multigraphFeedlist[parseInt(i,10)]["intervaltype"];
        }
        
        // Initialize plotdata array slot if needed
        if (typeof plotdata[parseInt(i,10)] === "undefined") {
          plotdata[parseInt(i,10)] = [];
        }

        // Abort any pending AJAX request for this feed to prevent race conditions
        // This happens when user changes time window before previous request completes
        if (typeof ajaxAsyncXdr[parseInt(i,10)] !== "undefined") {
          ajaxAsyncXdr[parseInt(i,10)].abort(); // Abort pending loads
          ajaxAsyncXdr[parseInt(i,10)]="undefined";
        }
        
        // Create context object to pass to callback
        // This allows callback to know which feed the data belongs to
        var context = {index:i, plotlist:plotlist[parseInt(i,10)]};
        
        /**
         * Initiate asynchronous AJAX request to fetch feed data
         * 
         * feed.getdata() Parameters:
         * @param {number} feedid - Feed ID to fetch
         * @param {number} start - Start timestamp (milliseconds since epoch)
         * @param {number} end - End timestamp (milliseconds since epoch)
         * @param {number|string} interval - Data interval in seconds, or "daily" for daily data
         * @param {number} average - Average mode: 0=off, 1=on (calculate mean over interval)
         * @param {number} delta - Delta mode: 0=off, 1=on (calculate difference between points)
         * @param {number} skipmissing - Skip missing data: 0=include nulls, 1=skip nulls
         * @param {number} limitinterval - Limit interval mode: 0=off, 1=on
         * @param {function} callback - Callback function(context, data) called on completion
         * @param {object} context - Context object passed to callback
         * 
         * Returns: XMLHttpRequest object (stored in ajaxAsyncXdr[] for abort capability)
         * 
         * Callback receives:
         * - context: The context object passed here
         * - data: Array of [timestamp, value] pairs, or null/undefined on error
         */
        ajaxAsyncXdr[parseInt(i,10)] = feed.getdata(
          plotlist[parseInt(i,10)].id,  // Feed ID
          view.start,                    // Start time (ms)
          view.end,                      // End time (ms)
          interval,                      // Data interval (seconds or "daily")
          average,                       // Average mode
          delta,                         // Delta mode
          skipmissing,                   // Skip missing data
          1,                            // Limit interval mode
          visFeedDataCallback,          // Success callback
          context                        // Context for callback
        );
      }
    }
  }
}

/**
 * Callback function called when AJAX feed data request completes.
 * 
 * This function is invoked by feed.getdata() when the HTTP request finishes.
 * It updates the plotdata array with the received data and triggers a graph re-render.
 * 
 * Error Handling:
 * - If data is null/undefined (request failed), the feed won't be plotted
 * - Check data validity before assigning to prevent errors
 * - Failed feeds simply won't appear in the graph (graceful degradation)
 * 
 * @param {object} context - Context object passed from visFeedDataDelayed()
 *   - context.index: Feed index in plotlist
 *   - context.plotlist: Plot series configuration object
 * @param {Array|null|undefined} data - Feed data array or null/undefined on error
 *   Format: [[timestamp1, value1], [timestamp2, value2], ...]
 *   Timestamps are in milliseconds since epoch
 *   Values are numbers or null for missing data points
 */
function visFeedDataCallback(context,data) {
  var i = context["index"];

  // Store data in plot series configuration
  context["plotlist"].plot.data = data;
  
  // Only update plotdata if we received valid data
  // This prevents errors when AJAX request fails (data will be null/undefined)
  if (context["plotlist"].plot.data) {
    plotdata[parseInt(i,10)] = context["plotlist"].plot;
  }
  // Note: If data is invalid, the feed simply won't be plotted
  // This is graceful degradation - other feeds will still display

  // Re-render graph with updated data
  // This is called for each feed as its data arrives (may cause multiple renders)
  // Flot.js handles this efficiently
  plot();
}

/**
 * Renders the graph using Flot.js plotting library.
 * 
 * This function is called whenever plotdata changes or view settings update.
 * It configures Flot.js with the current plotdata array and view settings.
 * 
 * @see plotdata - Global array of plot series, updated by visFeedDataCallback()
 * @see view - Global view object from vis.helper.js, contains time window and axis limits
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
      min: view.start,               // Minimum time (ms)
      max: view.end                  // Maximum time (ms)
    },
    selection: { mode: "x" },        // Allow selecting time range by dragging
    legend: { 
      show: showlegend,              // Show/hide legend based on global flag
      position: "nw",                // Position: northwest
      toggle: true                   // Allow clicking legend items to show/hide series
    },
    toggle: { scale: "visible" },     // Toggle series visibility
    touch: { 
      pan: "x",                      // Allow panning on touch devices (x-axis only)
      scale: "x",                    // Allow pinch-to-zoom (x-axis only)
      simulClick: false              // Disable simultaneous click detection
    },
    yaxis: { 
      min: view.ymin,                // Left Y-axis minimum (null = auto)
      max: view.ymax                // Left Y-axis maximum (null = auto)
    },
    y2axis: { 
      min: view.y2min,               // Right Y-axis minimum (null = auto)
      max: view.y2max                // Right Y-axis maximum (null = auto)
    }
  });
}


/**
 * Initializes the multigraph visualization.
 * 
 * This is the main initialization function called when the visualization loads.
 * It sets up the HTML structure, event handlers, and initial data fetch.
 * 
 * Initialization Steps:
 * 1. Set default time window (7 days, or from multigraphFeedlist config)
 * 2. Create HTML structure for graph and controls
 * 3. Set up event handlers (zoom, pan, time selection, etc.)
 * 4. Initialize datetime pickers
 * 5. Set up tooltip hover handlers
 * 6. Trigger initial data fetch
 * 
 * @param {jQuery|string} element - jQuery selector or element where graph will be rendered
 */
function multigraphInit(element) {
  // Initialize plotdata array
  plotdata = [];
  
  // Set default time window: 7 days ending at current time
  // This can be overridden by multigraphFeedlist[0] configuration
  var timeWindow = (3600000*24.0*7); // 7 days in milliseconds
  var now = new Date().getTime();
  view.start = now - timeWindow;
  view.end = now;

  if (typeof multigraphFeedlist !== "undefined" && typeof multigraphFeedlist[0] !== "undefined") {
    view.end = multigraphFeedlist[0].end;
    if (view.end === 0) {view.end = now;}
    if (multigraphFeedlist[0].timeWindow) {
        view.start = view.end - multigraphFeedlist[0].timeWindow;
    }
  }

  var out =
    "<div id='graph_bound' style='height:400px; width:100%; position:relative; '>"+
      "<div id='graph'></div>"+

      "<div id='graph-buttons-timemanual' style='position:absolute; top:15px; right:35px; opacity:0.5; display: none;'>"+
        "<div class='input-prepend input-append'>"+
            "<span class='add-on'>Select time window</span>"+

            "<span class='add-on'>Start:</span>"+
            "<span id='datetimepicker1'>"+
                "<input id='timewindow-start' data-format='dd/MM/yyyy hh:mm:ss' type='text' style='width:140px'/>"+
                "<span class='add-on'><i data-time-icon='icon-time' data-date-icon='icon-calendar'></i></span>"+
            "</span> "+

            "<span class='add-on'>End:</span>"+
            "<span id='datetimepicker2'>"+
                "<input id='timewindow-end' data-format='dd/MM/yyyy hh:mm:ss' type='text' style='width:140px'/>"+
                "<span class='add-on'><i data-time-icon='icon-time' data-date-icon='icon-calendar'></i></span>"+
            "</span> "+

            "<button class='btn graph-timewindow-set' type='button'><i class='icon-ok'></i></button>"+
        "</div> "+
      "</div>"+

      "<div id='graph-buttons' style='position:absolute; top:15px; right:50px; opacity:0.5; display: none;'>"+
        "<div id='graph-buttons-normal'>"+
            "<div class='input-prepend input-append' id='graph-tooltip' style='margin:0'>"+
             "<span class='add-on'>Tooltip:</span>"+
             "<span class='add-on'><input id='enableTooltip' type='checkbox' checked ></span>"+
            "</div> "+

            "<div class='btn-group'>"+
             "<button class='btn graph-time' type='button' time='1'>D</button>"+
             "<button class='btn graph-time' type='button' time='7'>W</button>"+
             "<button class='btn graph-time' type='button' time='30'>M</button>"+
             "<button class='btn graph-time' type='button' time='365'>Y</button>"+
             "<button class='btn graph-timewindow' type='button'><i class='icon-resize-horizontal'></i></button></div>"+

            "<div class='btn-group' id='graph-navbar' style='display: none;'>"+
             "<button class='btn graph-nav' id='zoomin'>+</button>"+
             "<button class='btn graph-nav' id='zoomout'>-</button>"+
             "<button class='btn graph-nav' id='left'><</button>"+
             "<button class='btn graph-nav' id='right'>></button></div>"+

            "<div class='btn-group'>"+
             "<button class='btn graph-exp' id='graph-fullscreen' type='button'><i class='icon-resize-full'></i></button></div>"+

        "</div>"+
      "</div>"+
    "</div>";
  $(element).html(out);

  // Tool tip
  var previousPoint = null;
  var previousSeries = null;
  $(element).bind("plothover", function (event, pos, item) {
    //$("#x").text(pos.x.toFixed(2));
    //$("#y").text(pos.y.toFixed(2));

    if ($("#enableTooltip:checked").length > 0) {
      if (item) {
        if (previousPoint !== item.dataIndex || previousSeries !== item.seriesIndex) {
          previousPoint = item.dataIndex;
          previousSeries = item.seriesIndex;

          $("#tooltip").remove();
          var x = item.datapoint[0].toFixed(2);
          var y;
          var options;
          if (typeof(item.datapoint[2])==="undefined") {
            y=Number(item.datapoint[1].toFixed(2));
          } else {
            y=Number((item.datapoint[1]-item.datapoint[2]).toFixed(2));
          }


          options = { month:"short", day:"2-digit", hour:"2-digit", minute:"2-digit", second:"2-digit"};
          // options = { month:"short", day:"2-digit"}; daily data?

          var formattedTime=new Date(parseInt(x,10));

          // I'd like to eventually add colour hinting to the background of the tooltop.
          // This is why showTooltip has the bgColour parameter.
          tooltip(item.pageX, item.pageY, item.series.label + " at " + formattedTime.toLocaleDateString("en-GB",options) + " = " + y, "#DDDDDD");
        }
      } else {
        $("#tooltip").remove();
        previousPoint = null;
      }
    }
  });

  function visResize() {
    var width = $("#graph_bound").width();
    $("#graph").width(width);
    var height = width * 0.5;

    if (embed) {
        $("#graph").height($(window).height());
    } else {
        $("#graph").height(height);
    }
    plot();
  }

  visResize();

  $(document).on("window.resized hidden.sidebar.collapse shown.sidebar.collapse",visResize);

  // Graph selections
  $("#graph").bind("plotselected", function (event, ranges) {
     view.start = ranges.xaxis.from;
     view.end = ranges.xaxis.to;
     visFeedData();
  });

  // Navigation actions
  $("#zoomout").click(function () {view.zoomout(); visFeedData();});
  $("#zoomin").click(function () {view.zoomin(); visFeedData();});
  $("#right").click(function () {view.panright(); visFeedData();});
  $("#left").click(function () {view.panleft(); visFeedData();});
  $("#graph-fullscreen").click(function () {view.fullscreen();});
  $(".graph-time").click(function () {view.timewindow($(this).attr("time")); visFeedData();});

  $(".graph-timewindow").click(function () {
     $("#graph-buttons-timemanual").show();
     $("#graph-buttons-normal").hide();
  });

  $(".graph-timewindow-set").click(function () {
    var timewindowStart = parse_timepicker_time($("#timewindow-start").val());
    var timewindowEnd = parse_timepicker_time($("#timewindow-end").val());
    if (!timewindowStart) {alert("Please enter a valid start date."); return false; }
    if (!timewindowEnd) {alert("Please enter a valid end date."); return false; }
    if (timewindowStart>=timewindowEnd) {alert("Start date must be further back in time than end date."); return false; }

    $("#graph-buttons-timemanual").hide();
    $("#graph-buttons-normal").show();
    view.start = timewindowStart * 1000;
    view.end = timewindowEnd *1000;
    visFeedData();
  });

  $("#datetimepicker1").datetimepicker({
    language: "en-EN"
  });

  $("#datetimepicker2").datetimepicker({
    language: "en-EN",
    useCurrent: false //Important! See issue #1075
  });

  $("#datetimepicker1").on("changeDate", function (e) {
    if (view.datetimepicker_previous == null) {view.datetimepicker_previous = view.start;}
    if (Math.abs(view.datetimepicker_previous - e.date.getTime()) > 1000*60*60*24)
    {
        var d = new Date(e.date.getFullYear(), e.date.getMonth(), e.date.getDate());
        d.setTime( d.getTime() - e.date.getTimezoneOffset()*60*1000 );
        out = d;
        $("#datetimepicker1").data("datetimepicker").setDate(out);
    } else {
        out = e.date;
    }
    view.datetimepicker_previous = e.date.getTime();

    $("#datetimepicker2").data("datetimepicker").setStartDate(out);
  });

  $("#datetimepicker2").on("changeDate", function (e) {
    if (view.datetimepicker_previous === null) {view.datetimepicker_previous = view.end;}
    if (Math.abs(view.datetimepicker_previous - e.date.getTime()) > 1000*60*60*24)
    {
        var d = new Date(e.date.getFullYear(), e.date.getMonth(), e.date.getDate());
        d.setTime( d.getTime() - e.date.getTimezoneOffset()*60*1000 );
        out = d;
        $("#datetimepicker2").data("datetimepicker").setDate(out);
    } else {
        out = e.date;
    }
    view.datetimepicker_previous = e.date.getTime();

    $("#datetimepicker1").data("datetimepicker").setEndDate(out);
  });

  datetimepicker1 = $("#datetimepicker1").data("datetimepicker");
  datetimepicker2 = $("#datetimepicker2").data("datetimepicker");

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
    visFeedData();
  });
}
