<?php
/**
 * Visualization Configuration Registry
 * 
 * This file defines all available visualization types and their configuration schemas.
 * It serves as the central registry that the vis module uses to:
 * - Generate the visualization selection UI in vis_main_view.php
 * - Validate parameters in vis_controller.php
 * - Provide metadata for widget rendering
 * 
 * How It Works:
 * =============
 * The $visualisations array maps visualization keys (route names) to their configuration.
 * When a user accesses /vis/{key}, the controller looks up the key in this array to:
 * 1. Validate required parameters
 * 2. Extract and sanitize optional parameters
 * 3. Load the corresponding visualization file from visualisations/{key}.php
 * 
 * Array Structure:
 * ===============
 * $visualisations = array(
 *     'visualization_key' => array(
 *         'label' => 'Display Name',           // Human-readable name (translated)
 *         'action' => 'route_override',        // Optional: override route key
 *         'options' => array(                   // Array of parameter definitions
 *             array('param_name', 'label', type_code, default_value),
 *             ...
 *         )
 *     ),
 *     ...
 * );
 * 
 * Option Array Format:
 * ===================
 * Each option is defined as: array('param_name', 'translated_label', type_code, default_value)
 * 
 * - param_name: URL parameter name (e.g., 'feedid', 'colour', 'units')
 * - translated_label: Display label (uses ctx_tr() for internationalization)
 * - type_code: Parameter type (see Parameter Types below)
 * - default_value: Default value if parameter not provided (optional, can be omitted)
 * 
 * Parameter Types:
 * ===============
 * Type codes define how parameters are validated and processed by vis_controller.php:
 * 
 * 0 - Feed (realtime or daily): Accepts any feed type
 *     Validation: Checks feed exists, belongs to user, or is public
 *     Returns: Feed ID (integer)
 * 
 * 1 - Feed (realtime only): Requires realtime feed
 *     Validation: Same as type 0, but also checks feed engine type
 *     Returns: Feed ID (integer)
 * 
 * 2 - Feed (daily only): Requires daily feed
 *     Validation: Same as type 0, but checks for daily feed type
 *     Returns: Feed ID (integer)
 * 
 * 4 - Boolean: True/false value
 *     Validation: Converts "true", "1" to 1, "false", "0" to 0
 *     Returns: 0 or 1 (integer)
 * 
 * 5 - Text: String value
 *     Validation: Sanitizes with regex (removes special chars except allowed Unicode)
 *     Returns: Sanitized string
 * 
 * 6 - Float: Decimal number
 *     Validation: Converts to float, replaces comma with dot
 *     Returns: Float value
 * 
 * 7 - Integer: Whole number
 *     Validation: Converts to integer
 *     Returns: Integer value
 * 
 * 8 - Multigraph ID: References a multigraph
 *     Validation: Checks multigraph exists and belongs to user
 *     Returns: Multigraph ID (integer)
 * 
 * 9 - Colour: Color value (hex code or color name)
 *     Validation: Removes invalid characters, ensures hex format
 *     Returns: Color string (hex code without #)
 * 
 * Special Properties:
 * ===================
 * - 'action' property: Overrides the visualization key for routing
 *   Example: 'multigraph' uses action='multigraph' to route to multigraph.php
 *   instead of looking for visualisations/multigraph.php
 * 
 * Usage in Controller:
 * ===================
 * vis_controller.php processes these options in the following way:
 * 1. Extracts each option from URL parameters using get($param_name)
 * 2. Validates based on type_code
 * 3. Stores validated values in $array[$param_name]
 * 4. Passes $array to visualization template file
 * 
 * Adding New Visualizations:
 * ==========================
 * 1. Add entry to $visualisations array with unique key
 * 2. Define 'label' (translated display name)
 * 3. Define 'options' array with all parameters
 * 4. Create visualization file: visualisations/{key}.php
 * 5. Add translation strings to locale files if needed
 * 
 * Example:
 * --------
 * 'myvis' => array(
 *     'label' => ctx_tr('vis_messages', 'My Visualization'),
 *     'options' => array(
 *         array('feedid', ctx_tr('vis_messages', 'feed'), 1),      // Required feed
 *         array('colour', ctx_tr('vis_messages', 'colour'), 9, 'FF0000'),  // Optional color
 *         array('showlegend', ctx_tr('vis_messages', 'show legend'), 4, true)  // Optional boolean
 *     )
 * )
 * 
 * Dependencies:
 * ============
 * - Used by: vis_controller.php (parameter validation)
 * - Used by: vis_main_view.php (UI generation)
 * - Used by: vis/widget/vis_render.js (widget rendering)
 * - Requires: locale/vis_messages translation files for labels
 * 
 * TODO:
 * =====
 * - Consider moving this to PHP source format for vis/widget/vis_render.js
 * - Currently duplicated in JavaScript for widget rendering
 */

    /* Parameter Type Codes:
      0 - feed realtime or daily
      1 - feed realtime
      2 - feed daily
      4 - boolean
      5 - text
      6 - float value
      7 - int value
      8 - multigraph id
      9 - colour
    */

    // TODO: This array is currently used in vis_main_view.php for UI generation.
    // Consider refactoring to provide PHP source for vis/widget/vis_render.js vis_widgetlist variable data
    // to avoid duplication between PHP and JavaScript.

    /**
     * Visualization Registry Array
     * 
     * Maps visualization keys to their configuration schemas.
     * Each entry defines the parameters, types, and defaults for a visualization type.
     * 
     * @var array Associative array: 'key' => array('label' => ..., 'options' => ...)
     */
    /* fixed some typo, added space in between words*/

    $visualisations = array(
    
        'realtime' => array('label'=>ctx_tr('vis_messages','Real Time'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','background colour'),9,'ffffff'),//renamed for clarity
            array('kw',ctx_tr('vis_messages','kW'),4,false),
            )
        ),
        
        // Hex colour EDC240 is the default color for flot. since we want existing setups to not change, we set the default value to it manually now,
        'rawdata'=> array('label'=>ctx_tr('vis_messages','Raw Data'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('fill',ctx_tr('vis_messages','fill'),7,0),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','colourbg'),9,'ffffff'),
            array('units',ctx_tr('vis_messages','units'),5,''),
            array('dp',ctx_tr('vis_messages','dp'),7,'2'),
            array('scale',ctx_tr('vis_messages','scale'),6,'1'),
            array('average',ctx_tr('vis_messages','average'),4,'0'),
            array('delta',ctx_tr('vis_messages','delta'),4,'0'),
            array('skipmissing',ctx_tr('vis_messages','skipmissing'),4,'1')
            )
        ),
        
        'bargraph'=> array('label'=>ctx_tr('vis_messages','Bar Graph'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),0),
            array('colour',ctx_tr('vis_messages','colour'),9,'EDC240'),
            array('colourbg',ctx_tr('vis_messages','colourbg'),9,'ffffff'),
            array('interval',ctx_tr('vis_messages','interval'),7,'86400'),
            array('units',ctx_tr('vis_messages','units'),5,''),
            array('dp',ctx_tr('vis_messages','dp'),7,'1'),
            array('scale',ctx_tr('vis_messages','scale'),6,'1'),
            array('average',ctx_tr('vis_messages','average'),4,'0'),
            array('delta',ctx_tr('vis_messages','delta'),4,'0')
            )
        ),
        
        'zoom'=> array('label'=>ctx_tr('vis_messages','Zoom'), 'options'=>array(
            array('power',ctx_tr('vis_messages','power'),1),
            array('kwhd',ctx_tr('vis_messages','kwhd'),0),
            array('currency',ctx_tr('vis_messages','currency'),5,'&pound;'),
            array('currency_after_val',ctx_tr('vis_messages','currency_after_val'),7, 0),
            array('pricekwh',ctx_tr('vis_messages','pricekwh'),6,0.14),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        //rearranged and renamed stacked options for better clarity
        'stacked'=> array('label'=>ctx_tr('vis_messages','Stacked'), 'options'=>array(
            array('top',ctx_tr('vis_messages','Top Feed'),0),
            array('colourt',ctx_tr('vis_messages','Top Colour'),9,'7CC9FF'),
            array('bottom',ctx_tr('vis_messages','Bottom Feed'),0),
            array('colourb',ctx_tr('vis_messages','Bottom Feed'),9,'0096FF'),
            array('delta',ctx_tr('vis_messages','Delta'),4,0)
        )),
        
        'stackedsolar'=> array('label'=>ctx_tr('vis_messages','Stacked Solar'), 'options'=>array(
            array('solar',ctx_tr('vis_messages','solar'),0),
            array('consumption',ctx_tr('vis_messages','consumption'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'simplezoom'=> array('label'=>ctx_tr('vis_messages','Simple Zoom'), 'options'=>array(
            array('power',ctx_tr('vis_messages','power'),1),
            array('kwhd',ctx_tr('vis_messages','kwh'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'orderbars'=> array('label'=>ctx_tr('vis_messages','Order Bars'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),0),
            array('delta',ctx_tr('vis_messages','delta'),4,0)
        )),
        
        'multigraph' => array ('label'=>ctx_tr('vis_messages','Multigraph'), 'action'=>'multigraph', 'options'=>array(
            array('mid',ctx_tr('vis_messages','mid'),8)
        )),
        
        'editor'=> array('label'=>ctx_tr('vis_messages','Editor'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1)
        )),
        
        // --------------------------------------------------------------------------------
        // Not currently available on emoncms.org
        // --------------------------------------------------------------------------------     
        'smoothie'=> array('label'=>ctx_tr('vis_messages','Smoothie'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('ufac',ctx_tr('vis_messages','ufac'),6))
        ),

        'compare' => array ('label'=>ctx_tr('vis_messages','Compare'), 'action'=>'compare', 'options'=>array(
            array('feedA',ctx_tr('vis_messages','Feed A'),1),
            array('feedB',ctx_tr('vis_messages','Feed B'),1)
        )),
        
        'timecompare'=> array('label'=>ctx_tr('vis_messages','Time Comparison'), 'options'=>array(
            array('feedid',ctx_tr('vis_messages','feed'),1),
            array('fill',ctx_tr('vis_messages','fill'),7,1),
            array('depth',ctx_tr('vis_messages','depth'),7,3),
            array('npoints',ctx_tr('vis_messages','data points'),7,800)
        )),
		
        // --------------------------------------------------------------------------------
        // psychrographic diagrams to appreciate summer confort
        // --------------------------------------------------------------------------------
        'psychrograph' => array ('label'=>ctx_tr('vis_messages','Psychrometric Diagram'), 'action'=>'psychrograph', 'options'=>array(
            array('mid',ctx_tr('vis_messages','mid'),8),
            array('hrtohabs',ctx_tr('vis_messages','% to abso.'),4, 1),
            array('givoni',ctx_tr('vis_messages','givoni style?'),4)
        ))
        // --------------------------------------------------------------------------------     
    );
