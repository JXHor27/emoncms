  /*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
  */

/**
 * Comparison Visualization JavaScript (D3.js version)
 * 
 * This file handles rendering monthly comparison charts using D3.js.
 * It displays daily energy consumption (kWh/d) as bar charts for a specific month,
 * allowing users to compare two different months side-by-side.
 * 
 * Data Flow:
 * 1. plotChart() is called with month/year to display
 * 2. Makes AJAX request to Feed API for daily data for that month
 * 3. On success, renders D3.js bar chart with navigation controls
 * 4. User can click bars to see daily details and comparison
 * 
 * Dependencies:
 * - D3.js - For SVG-based chart rendering
 * - jQuery - For AJAX requests and DOM manipulation
 * - Feed API - /feed/data.json endpoint for fetching feed data
 * 
 * Global Variables (defined in parent PHP file):
 * - path: Base path for API endpoints
 * - apikey: API key for authentication
 * - kwhd: Feed ID for daily energy data
 * - year: Current year
 * - price: Energy price per kWh
 * - currency: Currency symbol
 * - kwhd1, kwhd2: Selected daily values for comparison
 */

/**
 * Plots a monthly bar chart showing daily energy consumption.
 * 
 * This function:
 * 1. Calculates the date range for the specified month
 * 2. Fetches daily kWh data via AJAX from Feed API
 * 3. Renders a D3.js bar chart with navigation controls
 * 4. Sets up click handlers for bar interactions
 * 
 * AJAX Pattern:
 * - Uses jQuery $.ajax() directly (not feed.getdata() wrapper)
 * - Makes GET request to /feed/data.json endpoint
 * - Includes API key and feed parameters in query string
 * - Success callback receives array of [timestamp, value] pairs
 * 
 * Error Handling:
 * - AJAX errors are not explicitly handled (silent failure)
 * - Consider adding error callback for production use
 * 
 * @param {d3.selection} container - D3 selection where chart will be rendered
 * @param {number} id - Chart identifier (1 or 2 for side-by-side comparison)
 * @param {number} month - Month number (0-11, where 0=January, 11=December)
 */
function plotChart(container, id, month) {
  // Calculate date range for the specified month
  // End date: Last day of month at midnight
  var endDate = new Date(year, month, 31, 0, 0, 0, 0);

  // Start date: First day of month at midnight
  var startDate = new Date();
  var startDate = new Date(year, month, 1, 0, 0, 0, 0);

  /**
   * AJAX Request to Fetch Daily Energy Data
   * 
   * Makes HTTP GET request to Feed API endpoint.
   * 
   * Endpoint: path + "feed/data.json"
   * 
   * Parameters (query string):
   * - apikey: API key for authentication
   * - id: Feed ID (kwhd variable)
   * - start: Start timestamp in milliseconds (subtract 100ms to get previous day's last point)
   * - end: End timestamp in milliseconds
   * - dp: Data points parameter (30 = daily aggregation)
   * 
   * Response Format:
   * Array of [timestamp, value] pairs:
   * [[timestamp1, kWh1], [timestamp2, kWh2], ...]
   * 
   * Error Handling:
   * - No explicit error callback (silent failure)
   * - Consider adding .fail() handler for production
   */
  $.ajax({
    url: path+"feed/data.json",
    data: "&apikey=" + apikey + "&id=" + kwhd + "&start=" + (startDate.getTime() - 100)  +"&end=" + endDate.getTime() + "&dp=30", // - 100 is to get the day before the first
    dataType: 'json',
    success: function(data_in)
    {
      // Store received data globally
      data = data_in;
      
      // Chart dimensions
      var w = 500,  // Chart width
        h = 200,    // Chart height
        p = 30;      // Padding

      // Calculate maximum Y value for scaling
      var ymax = 0;
      for(var i in data) {
        if( data[i][1] > ymax ) {
          ymax = data[i][1];
        }
      }


      // Scales and axes. Note the inverted domain for the y-scale: bigger is up!
      var x = d3.time.scale().domain([startDate, endDate]).range([0, w]),
        y = d3.scale.linear().domain([0, ymax]).range([h, 0])

        xAxis = d3.svg.axis().scale(x).tickSubdivide(true).orient("bottom").ticks(6).tickFormat(d3.time.format("%d %B")),
        yAxis = d3.svg.axis().scale(y).ticks(10).orient("left");

      var prev = container.append("a")
        .attr("id", "prev"+id)
        .attr("href", "#")
        .attr("style", "margin: 120px 0 0 10px; background-image: url(\"../../Views/theme/wp/prev.png\"); display: block; height: 24px; position: absolute; width: 24px; z-index: 10;");

      // Previous month navigation button
      // Clicking loads the previous month's data
      $("#prev"+id).click(function () {
        container.html("");  // Clear current chart
        plotChart(container, id, month-1, year);  // Recursively load previous month
      });

      // Next month navigation button
      var next = container.append("a")
        .attr("id", "next"+id)
        .attr("href", "#")
        .attr("style", "margin: 120px 0 0 570px; background-image: url(\"../../Views/theme/wp/next.png\"); display: block; height: 24px; position: absolute; width: 24px; z-index: 10;");

      // Next month click handler
      $("#next"+id).click(function () {
        container.html("");  // Clear current chart
        plotChart(container, id, month+1);  // Recursively load next month
      });

      var vis = container
        .append("svg")
        .data([data])
        .attr("id", "#chart"+id)
        .attr("class", "chart")
        .attr("width", w + p * 2)
        .attr("height", h + p * 2)
        .append("g")
        .attr("transform", "translate(" + p + "," + p + ")");

      var rules = vis.selectAll("g.rule")
      .data(y.ticks(10))
      .enter().append("g")
        .attr("class", "rule");

      // Create bar chart: one rectangle (bar) for each day
      var bars = vis.selectAll("rect")
        .data(data)  // Bind data array to selection
        .enter().append("rect")  // Create new rectangles for data points
          .attr("x", function(d) { return x(d[0]); })  // X position based on timestamp
          .attr("y", function(d) { return y(d[1]); })  // Y position based on value
          .attr("id", function(d) { return "index-" + d[0]; })  // Unique ID for hover effects
          .attr("width", 10)  // Bar width
          .attr("height", function(d) { return h - y(d[1]); })  // Bar height (inverted Y scale)
          .on("mouseover", fade(0.6))  // Fade on hover (60% opacity)
          .on("mouseout", fade(0.4))   // Fade on mouseout (40% opacity)
          .on("click",  function(d) { render_daily_information(id, d[0], d[1]); });  // Show details on click

      vis.append("g")
        .attr("class", "x axis")
        .attr("transform", "translate(0," + h + ")")
        .call(xAxis.tickSubdivide(0).tickSize(0));

      // Add the y-axis.
      vis.append("g")
        .attr("class", "y axis")
        .call(yAxis.tickSize(0));

      rules.append("line")
        .attr("class", function(d) { return d ? null : "axis"; })
        .attr("y1", y)
        .attr("y2", y)
        .attr("x1", 0)
        .attr("x2", w + 1);

      /**
       * Returns an event handler function for bar fade effects.
       * 
       * Used for hover interactions: when mouse enters/leaves a bar,
       * this function creates a smooth opacity transition.
       * 
       * @param {number} opacity - Target opacity (0.0 to 1.0)
       * @returns {function} Event handler function
       */
      function fade(opacity) {
        return function(d, i) {
          // Select the bar by its unique ID and animate opacity
          vis.selectAll("#index-" + d[0])
            .transition()  // D3 transition for smooth animation
            .style("fill-opacity", opacity);
         };
      }
    }
  });
}

/**
 * Renders daily energy information and comparison when a bar is clicked.
 * 
 * This function:
 * 1. Displays detailed information for the selected day (date, energy, cost)
 * 2. Stores the selected value for comparison (kwhd1 or kwhd2)
 * 3. If both months have selections, calculates and displays the difference
 * 
 * Comparison Logic:
 * - kwhd1: Selected value from first chart (id=1)
 * - kwhd2: Selected value from second chart (id=2)
 * - When both are set, calculates difference and displays with color coding:
 *   - Green: Negative difference (savings)
 *   - Orange: Positive difference (increase)
 * 
 * Error Handling:
 * - No validation of input parameters
 * - Assumes price and currency globals exist
 * - Division by zero not checked (should be safe in this context)
 * 
 * @param {number} id - Chart identifier (1 or 2)
 * @param {number} timestamp - Timestamp of selected day (milliseconds)
 * @param {number} kwhd - Daily energy consumption in kWh
 */
function render_daily_information(id, timestamp, kwhd) {
  // Format date for display
  var d = new Date(timestamp);
  
  // Build HTML table with daily information
  var out = '<table style="text-align:left; margin: auto;">'
  out += '<tr><th>Date :</th><td id="date">' + d.toDateString()  + '</td></tr>'
  out += '<tr><th>Energy :</th><td id="kwhd">' + parseFloat(kwhd).toFixed(2)  + ' kWh/d</td></tr>';
  // Calculate daily and annual cost
  out += '<tr><th>Cost :</th><td id="costd">'+ parseFloat(kwhd * price).toFixed(2) + currency + '/d, ' + parseFloat(kwhd * price * 365).toFixed(0) + currency + '/y</td></tr>';
  out += '</table>';

  // Display in the appropriate day info container with fade animation
  $('#day' + id).each(function(index)
  {
    $(this).hide().html(out).fadeIn();
  });

  // Color coding for comparison
  var orange = "#FF7D14";  // Positive difference (increase)
  var green = "#C0E392";    // Negative difference (savings)

  // Store selected value for comparison
  if (id == 1)
    kwhd1 = kwhd;  // First chart selection
  else
    kwhd2 = kwhd;  // Second chart selection

  color = green;  // Default to green (savings)

  // Calculate and display comparison if both charts have selections
  if(kwhd1 != 0 && kwhd2 != 0) {
    // Calculate difference: kwhd2 - kwhd1
    var result = kwhd2 - kwhd1;
    var result2;
    
    if (result > 0) {
      // Positive difference: consumption increased
      result = "+" +  parseFloat(result).toFixed(2);
      result2 = "+" + parseFloat(result * price).toFixed(2);
      color = orange;  // Orange for increase
    }
    else {
      // Negative difference: consumption decreased (savings)
      result = parseFloat(result).toFixed(2);
      result2 = parseFloat(result * price).toFixed(2);
      // color stays green for savings
    }
    
    // Build comparison display HTML
    out = '<div class="comparison"><h1>Comparison</h1>';
    out += '<h2 style="color:' + color + ';\">' + result + 'kWh</h2>';
    out += '<h2 style ="color :#33A4D9;">' + result2 + currency + '/d (';
    out += parseFloat(result2 * 365).toFixed(0) + currency + '/y)</h2></div>';

    // Display comparison in comparison box
    $('#comparisonbox').each(function(index) {
      $(this).html(out);
    });
  }
}


