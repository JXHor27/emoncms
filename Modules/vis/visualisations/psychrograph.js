/**
 * Psychrometric Diagram Visualization - Zone Definition
 * 
 * This file defines the Zone class used to represent comfort zones
 * in psychrometric diagrams. Zones are used to visualize areas of
 * thermal comfort based on temperature and humidity.
 * 
 * A Zone is defined by:
 * - X-axis range (xmin, xmax) - temperature range
 * - Y-axis boundaries (ymin, ymax) - functions that calculate humidity
 *   boundaries at each temperature point
 */

/**
 * Zone constructor - defines a rectangular region with curved boundaries.
 * 
 * Used to represent comfort zones in psychrometric diagrams where
 * the humidity boundaries vary with temperature (not simple rectangles).
 * 
 * @param {number} xmin - Minimum X value (temperature)
 * @param {number} xmax - Maximum X value (temperature)
 * @param {function} ymin - Function(x) that returns minimum Y value (humidity) at temperature x
 * @param {function} ymax - Function(x) that returns maximum Y value (humidity) at temperature x
 * 
 * Example:
 *   var comfortZone = new Zone(
 *     20,                    // Min temp: 20°C
 *     25,                    // Max temp: 25°C
 *     function(x) { return 0.4 * x; },  // Min humidity curve
 *     function(x) { return 0.8 * x; }   // Max humidity curve
 *   );
 */
function Zone(xmin,xmax,ymin,ymax){
    this.xmin=xmin;  // Minimum temperature
    this.xmax=xmax;  // Maximum temperature
    this.ymin=ymin;  // Function: min humidity at temperature x
    this.ymax=ymax;  // Function: max humidity at temperature x
}

/**
 * Generates an array of [x, y] points that outline the zone boundary.
 * 
 * This method samples the zone boundaries at regular intervals and creates
 * a closed polygon path that can be used for drawing the zone outline.
 * 
 * The outline is created by:
 * 1. Sampling the bottom boundary (ymin) from xmin to xmax
 * 2. Sampling the top boundary (ymax) from xmax back to xmin
 * 3. Closing the path back to the start
 * 
 * @param {number} pas - Sampling step size (default: 0.5)
 *   Smaller values = smoother curves but more points
 * 
 * @returns {Array} Array of [x, y] coordinate pairs forming a closed polygon
 *   Format: [[x1, y1], [x2, y2], ...]
 * 
 * Example:
 *   var points = zone.outline(0.5);
 *   // Returns: [[20, 8], [20.5, 8.2], ..., [25, 10], [25, 20], ..., [20, 8]]
 */
Zone.prototype.outline = function(){
    var XY = [];  // Array to store outline points
    var pas = 0.5;  // Step size for sampling (0.5 units)
    var x = this.xmin;
    var i = 0;
    
    // Sample bottom boundary from xmin to xmax
    while (x < this.xmax) {
        XY[i] = [];
        XY[i][0] = x;              // X coordinate (temperature)
        XY[i][1] = this.ymin(x);   // Y coordinate (humidity) from bottom boundary function
        x += pas;  // Move to next sample point
        i += 1;
    }
    
    // Add final point on bottom boundary at xmax
    XY[i] = [];
    XY[i][0] = this.xmax;
    XY[i][1] = this.ymin(this.xmax);
    i += 1;
    
    // Sample top boundary from xmax back to xmin (reverse direction)
    while (x >= this.xmin) {
        XY[i] = [];
        XY[i][0] = x;              // X coordinate (temperature)
        XY[i][1] = this.ymax(x);   // Y coordinate (humidity) from top boundary function
        x -= pas;  // Move backward to previous sample point
        i += 1;
    }
    
    // Close the polygon by returning to start point
    XY[i] = [];
    XY[i][0] = this.xmin;
    XY[i][1] = this.ymin(this.xmin);
    
    return XY;
};

/**
 * Tests whether a point (x, y) is inside the zone.
 * 
 * Checks if a point falls within the zone boundaries:
 * 1. X coordinate must be between xmin and xmax
 * 2. Y coordinate must be between ymin(x) and ymax(x) at that temperature
 * 
 * @param {number} x - X coordinate (temperature)
 * @param {number} y - Y coordinate (humidity)
 * 
 * @returns {boolean} true if point is inside zone, false otherwise
 * 
 * Example:
 *   var inZone = zone.includes(22, 0.5);  // Check if 22°C, 50% humidity is in zone
 */
Zone.prototype.includes = function(x,y){
    // Check if X is outside temperature range
    if (x < this.xmin) {return false;}
    if (x > this.xmax) {return false;}
    
    // Check if Y is outside humidity range at this temperature
    // Calculate boundaries at this specific temperature
    if (y < this.ymin(x)) {return false;}  // Below minimum humidity curve
    if (y > this.ymax(x)) {return false;}  // Above maximum humidity curve
    
    // Point passed all checks - it's inside the zone
    return true;
};
