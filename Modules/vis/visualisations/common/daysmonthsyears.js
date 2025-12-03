  /*
   All Emoncms code is released under the GNU Affero General Public License.
   See COPYRIGHT.txt and LICENSE.txt.

    ---------------------------------------------------------------------
    Emoncms - open source energy visualisation
    Part of the OpenEnergyMonitor project:
    http://openenergymonitor.org
  */

/**
 * Utility Functions for Date/Time Range Filtering and Aggregation
 * 
 * This file provides helper functions for filtering and aggregating time-series data
 * by date ranges (days, months, years). Used primarily in visualization components
 * that need to display aggregated daily, monthly, or yearly data.
 * 
 * Data Format:
 * All functions expect data in format: [[timestamp1, value1], [timestamp2, value2], ...]
 * Timestamps are in milliseconds since epoch
 */

/**
 * Filters data array to include only points within a specified time range.
 * 
 * This is a basic filtering function used by other aggregation functions.
 * It creates a new array containing only data points that fall within
 * the specified start and end timestamps.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 * @param {number} start - Start timestamp (milliseconds, inclusive)
 * @param {number} end - End timestamp (milliseconds, exclusive)
 * 
 * @returns {Array} Filtered data array containing only points in range
 * 
 * Example:
 *   var data = [[1000, 10], [2000, 20], [3000, 30]];
 *   get_range(data, 1500, 2500);  // Returns [[2000, 20]]
 */
function get_range(data,start,end)
{
  var gdata = [];
  var index = 0;
  // Iterate through all data points
  for (var z in data) {
    // Include point if timestamp is within range [start, end)
    // Note: end is exclusive (point at end timestamp is not included)
    if (data[z][0] >= start && data[z][0] < end) {
      gdata[index] = data[z];
      index++;
    }
  }
  return gdata;
}

/**
 * Filters data to include only points from a specific month.
 * 
 * Uses Date.UTC() to calculate the exact start and end of the month,
 * accounting for different month lengths and leap years.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 * @param {number} month - Month number (0-11, where 0=January, 11=December)
 * @param {number} year - Year (e.g., 2024)
 * 
 * @returns {Array} Filtered data array for the specified month
 * 
 * Example:
 *   get_days_month(data, 0, 2024);  // January 2024
 */
function get_days_month(data,month,year)
{
  // Date.UTC(year, month, 0) = last day of previous month
  // Date.UTC(year, month+1, 0) = last day of current month
  // This gives us the full month range
  return get_range(data,Date.UTC(year,month,0),Date.UTC(year,month+1,0));
}

/**
 * Filters data to include only points from the last 30 days.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 * 
 * @returns {Array} Filtered data array for the last 30 days
 */
function get_last_30days(data)
{
  var d = new Date();  // Current date/time
  var s = d - (3600000*24*30);  // 30 days ago (30 * 24 hours * 3600000 ms)
  return get_range(data,s,d);
}


/**
 * Aggregates daily data into monthly totals.
 * 
 * This function processes an array of daily data points and groups them by month,
 * summing the values for each month. Useful for creating monthly summary views.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 *   Expected to be daily data points
 * 
 * @returns {Object} Object with two arrays:
 *   - data: Array of [timestamp, sum] pairs (one per month)
 *     Timestamp is first day of the month
 *   - days: Array of day counts (number of days with data in each month)
 * 
 * Example:
 *   Input: [[Jan 1, 10], [Jan 2, 20], [Feb 1, 15]]
 *   Output: {
 *     data: [[Jan 1 timestamp, 30], [Feb 1 timestamp, 15]],
 *     days: [2, 1]
 *   }
 */
function get_months(data)
{
  var gdata = [];
  gdata.data = [];  // Monthly aggregated data
  gdata.days = [];  // Number of days with data per month

  var sum=0,      // Running sum for current month
      s=0,        // Running count of days with data
      i=0;        // Month index
  var lmonth=0,   // Previous month (for detecting month changes)
      month=0,    // Current month
      year;       // Current year
  var tmp = [];
  var d = new Date();
  
  // Iterate through all data points
  for (var z in data)
  {
    lmonth = month;  // Store previous month

    // Extract month and year from timestamp
    d.setTime(data[z][0]);
    month = d.getMonth();
    year = d.getFullYear();

    // If month changed and not first iteration, save previous month's data
    if (month!=lmonth && z!=0)
    {
      var tmp = [];
      tmp[0] = Date.UTC(year,month-1,1);  // First day of previous month
      tmp[1] = sum;  // Total for that month
      // Note: daysInMonth calculation was removed, just using sum directly

      gdata.data[i] = tmp;
      gdata.days[i] = s;  // Number of days with data
      i++;
      sum = 0;  // Reset for new month
      s = 0;
    }

    // Add value to running sum (skip null values)
    if (data[z][1]!=null) {
      sum += parseFloat(data[z][1]);
      s++;  // Count days with valid data
    }
   }

  // Don't forget the last month!
  var tmp = [];
  tmp[0] = Date.UTC(year,month,1);  // First day of current month
  tmp[1] = sum;  // Total for last month
  gdata.data[i] = tmp;
  gdata.days[i] = s;

  return gdata;
}

/**
 * Aggregates daily data into monthly totals for a specific year.
 * 
 * First filters data to the specified year, then aggregates by month.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 * @param {number} year - Year to filter (e.g., 2024)
 * 
 * @returns {Object} Same format as get_months(), but only for specified year
 */
function get_months_year(data,year)
{
  // Filter to year range: Jan 1 of year to Jan 1 of next year
  data = get_range(data,Date.UTC(year,0,1),Date.UTC(year+1,0,1));
  return get_months(data);
}

/**
 * Aggregates daily data into yearly totals.
 * 
 * Similar to get_months(), but groups by year instead of month.
 * Processes all data and creates one data point per year.
 * 
 * @param {Array} data - Input data array: [[timestamp, value], ...]
 *   Expected to be daily data points
 * 
 * @returns {Object} Object with two arrays:
 *   - data: Array of [timestamp, sum] pairs (one per year)
 *     Timestamp is January 1st of the year
 *   - days: Array of day counts (number of days with data in each year)
 * 
 * Note: Filters out timestamps < 1000000 (likely invalid/placeholder data)
 */
function get_years(data)
{
  var years = [];
  years.data = [];  // Yearly aggregated data
  years.days = [];  // Number of days with data per year

  var sum=0,      // Running sum for current year
      s=0,        // Running count of days with data
      i=0;        // Year index
  var lyear=0,    // Previous year (for detecting year changes)
      year=0;     // Current year
  var tmp = [];
  var d = new Date();

  // Iterate through all data points
  for (var z in data)
  {
    // Filter out invalid timestamps (likely placeholders)
    if (data[z][0]>1000000){
      lyear = year;  // Store previous year

      d.setTime(data[z][0]);      // Get the date of the day
      year = d.getFullYear();     // Get the year of the day
      
      // If year changed and not first iteration, save previous year's data
      if (year!=lyear && z!=0) {
        years.data[i] = [Date.UTC(year-1,0,1), sum];  // Jan 1 of previous year
        years.days[i] = s;
        i++;
        sum = 0;  // Reset for new year
        s = 0;
      }

      // Add value to running sum (skip null values)
      if (data[z][1]!=null) {
        sum += parseFloat(data[z][1]);  // Add the day kwh/d value to the sum
        s++;                             // Increment day count
      }
    }
  }
  
  // Don't forget the last year!
  years.data[i] = [Date.UTC(year,0,1), sum];  // Jan 1 of current year
  years.days[i] = s;

  return years;
}

/**
 * Calculates the number of days in a given month.
 * 
 * Uses a clever trick: create a date for the 32nd day of the month,
 * which automatically rolls over to the next month. The difference
 * from 32 gives us the actual number of days.
 * 
 * @param {number} iMonth - Month number (0-11, where 0=January, 11=December)
 * @param {number} iYear - Year (accounts for leap years)
 * 
 * @returns {number} Number of days in the month (28-31)
 * 
 * Example:
 *   daysInMonth(1, 2024);  // February 2024 = 29 (leap year)
 *   daysInMonth(1, 2023);  // February 2023 = 28
 */
function daysInMonth(iMonth, iYear)
{
  // Date(32) of a month rolls over to next month
  // 32 - (day of next month's 1st) = days in current month
  return 32 - new Date(iYear, iMonth, 32).getDate();
}
