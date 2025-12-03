# Visualisation Module

## Module Overview

The Visualisation (vis) module provides a flexible framework for creating and displaying data visualizations from feeds and multigraphs in emoncms. It serves as the primary interface for users to view their energy monitoring data through various graph types and interactive visualizations.

**Key Concepts:**
- **Feeds**: Individual data streams (temperature, power, energy consumption, etc.) stored in the emoncms feed engine
- **Multigraphs**: Collections of feeds grouped together and displayed as a single visualization
- **Visualizations**: Different graph types (realtime, rawdata, bargraph, etc.) that render feed data in various formats

The module handles routing, parameter validation, authentication, and rendering of visualizations both within emoncms dashboards and as standalone pages accessible via URL.

---

## Data Flow

Understanding how data moves through the visualization system:

![data flow diagram vis](images/DFDSME.drawio.png)

**Key Components in Data Flow:**
- **vis_controller.php**: Routes requests, validates parameters, handles authentication
- **vis_object.php**: Defines available visualization types and their parameter schemas
- **visualisations/*.php**: Individual visualization implementations
- **Feed API**: Provides data access via JavaScript (feed.js)
- **Flot.js**: Primary graphing library for rendering charts

---

## File Structure

### Core Files

| File | Purpose |
|------|---------|
| `vis_controller.php` | Main controller handling all routing, parameter validation, authentication, and request processing. Routes requests to appropriate visualization files. |
| `vis_object.php` | Defines all available visualization types and their configuration options. Contains the `$visualisations` array that maps visualization keys to their parameters. |
| `multigraph_model.php` | Database model for multigraph operations (create, read, update, delete). Handles multigraph storage and retrieval from database. |
| `vis_schema.php` | Database schema definition for the multigraph table. Used during module installation. |
| `vis_menu.php` | Integrates visualization menu item into emoncms navigation. Adds "Visualization" link to setup menu. |
| `vis_langjs.php` | Generates JavaScript language/translation strings for client-side use. Provides internationalization support. |
| `module.json` | Module metadata including name, version, location, and available branches. |

### Directories

| Directory | Contents |
|-----------|----------|
| `visualisations/` | Individual visualization implementation files (PHP). Each file handles rendering a specific graph type (realtime, rawdata, bargraph, etc.). |
| `Views/` | UI templates and JavaScript files for the visualization interface. Includes `vis_main_view.php` (main control panel) and multigraph editing components. |
| `widget/` | Widget components for embedding visualizations in dashboards. Includes `vis_widget.php` and `vis_render.js`. |
| `locale/` | Translation files for internationalization support. |
| `images/` | Image assets used in documentation and visualizations. |

---

## Visualization Types

The module supports multiple visualization types, each defined in `vis_object.php`. Below is a complete reference:

| Visualization Key | Label | Description | Required Parameters | Optional Parameters |
|-------------------|-------|-------------|---------------------|---------------------|
| `realtime` | RealTime | Real-time streaming graph for live data | `feedid` (realtime feed) | `colour`, `colourbg`, `kw` |
| `rawdata` | RawData | Standard time-series graph with customizable options | `feedid` (realtime feed) | `fill`, `colour`, `colourbg`, `units`, `dp`, `scale`, `average`, `delta`, `skipmissing` |
| `bargraph` | BarGraph | Bar chart visualization for aggregated data | `feedid` (realtime or daily) | `colour`, `colourbg`, `interval`, `units`, `dp`, `scale`, `average`, `delta` |
| `zoom` | Zoom | Interactive zoomable graph with power and kWh display | `power` (realtime feed), `kwhd` (realtime or daily) | `currency`, `currency_after_val`, `pricekwh`, `delta` |
| `stacked` | Stacked | Stacked area chart comparing two feeds | `bottom`, `top` (both realtime or daily) | `colourt`, `colourb`, `delta` |
| `stackedsolar` | StackedSolar | Specialized stacked chart for solar generation vs consumption | `solar`, `consumption` (both realtime or daily) | `delta` |
| `simplezoom` | SimpleZoom | Simplified zoomable graph | `power` (realtime feed), `kwhd` (realtime or daily) | `delta` |
| `orderbars` | OrderBars | Ordered bar chart visualization | `feedid` (realtime or daily) | `delta` |
| `multigraph` | MultiGraph | Displays multiple feeds together in a single graph | `mid` (multigraph id) | - |
| `editor` | Editor | Feed data editor for manual corrections | `feedid` (realtime feed) | - |
| `smoothie` | Smoothie | Smooth scrolling real-time graph | `feedid` (realtime feed) | `ufac` |
| `compare` | Compare | Side-by-side comparison of two feeds | `feedA`, `feedB` (both realtime feeds) | - |
| `timecompare` | Time Comparison | Compares same feed across different time periods | `feedid` (realtime feed) | `fill`, `depth`, `npoints` |
| `psychrograph` | Psychrometric Diagram | Thermal comfort visualization using temperature and humidity | `mid` (multigraph id) | `hrtohabs`, `givoni` |

### Parameter Types

Parameters in visualization options use type codes (defined in `vis_object.php`):

| Type Code | Type | Description |
|-----------|------|-------------|
| 0 | Feed (realtime or daily) | Accepts either realtime or daily feed |
| 1 | Feed (realtime) | Requires realtime feed only |
| 2 | Feed (daily) | Requires daily feed only |
| 4 | Boolean | True/false value |
| 5 | Text | String value |
| 6 | Float | Decimal number |
| 7 | Integer | Whole number |
| 8 | Multigraph ID | References a multigraph by ID |
| 9 | Colour | Color value (hex code or color name) |

---

## Controller Routes

### Main Routes

#### `/vis/list`
Accesses the visualization control panel where users can create and configure new visualizations.

- **Access**: Requires write permission (`$session['write']`)
- **View**: Uses `Views/vis_main_view.php` to render the toolbox
- **Functionality**: 
  - Lists available visualizations
  - Provides feed selection
  - Allows configuration of visualization options
  - Generates visualization URLs

#### `/vis/{visualisation_key}`
Renders a specific visualization type. The `visualisation_key` must match a key defined in `vis_object.php`.

**Required Parameters:**
- For feed-based visualizations: `feedid=X` (where X is the feed ID)
- For multigraph visualizations: `mid=Y` (where Y is the multigraph ID)

**Optional Parameters:**
- `embed=0|1`: Controls embedding mode
  - `embed=1`: Full screen view / integration into dashboard
  - `embed=0`: Within the 'main' viewport of emoncms (default)
- `apikey=...`: Read-only API key for external access or visitors

**Examples:**
```
/vis/multigraph?mid=1
/vis/graph?feedid=1
/vis/rawdata?feedid=5&embed=1
/vis/multigraph?mid=1&embed=0&apikey=32_chars_apikey_read
```

### Special Routes

#### `/vis/auto` and `/vis/graph`
These routes are aliases that redirect to `rawdata` visualization:
- `/vis/auto?feedid=X` → `/vis/rawdata?feedid=X`
- `/vis/graph?feedid=X` → `/vis/rawdata?feedid=X`

### JSON API Routes

#### `/vis/multigraph.json`
JSON API endpoints for multigraph management:

- `GET /vis/multigraph/get.json?id=X`: Get multigraph by ID
- `GET /vis/multigraph/getlist.json`: List all multigraphs for current user
- `POST /vis/multigraph/new.json`: Create new multigraph (requires write permission)
- `POST /vis/multigraph/delete.json?id=X`: Delete multigraph (requires write permission)
- `POST /vis/multigraph/set.json`: Update multigraph (requires write permission)
  - Parameters: `id`, `feedlist` (JSON), `name`

---

## How Visualizations Work

### Request Processing Flow

1. **Route Matching**: `vis_controller.php` checks if the route action matches a visualization key from `vis_object.php`

2. **Parameter Validation**: For each option defined in the visualization:
   - Feed parameters (types 0,1,2): Validates feed exists, belongs to user, or is public
   - Multigraph parameters (type 8): Validates multigraph exists and belongs to user
   - Other parameters: Validated and sanitized according to their type

3. **Authentication Check**: 
   - Verifies user has access to requested feeds
   - Public feeds can be accessed without authentication
   - API key can be provided for external access

4. **Visualization Rendering**: 
   - If validation passes, loads the corresponding PHP file from `visualisations/` directory
   - Passes validated parameters as an array to the visualization template
   - If validation fails, displays error message

### Visualization File Structure

Each visualization file in `visualisations/` typically:

1. **Extracts URL Parameters**: Gets `feedid`, `mid`, `embed`, `apikey` from URL
2. **Includes JavaScript Libraries**: Loads Flot.js, feed.js, and helper libraries
3. **Renders HTML Structure**: Creates graph container and controls
4. **Initializes JavaScript**: Sets up data fetching and graph rendering
5. **Fetches Data**: Uses Feed API to retrieve time-series data
6. **Renders Graph**: Uses Flot.js or other library to display the visualization

Example parameter extraction pattern:
```javascript
var feedid = <?php echo $feedid; ?>;
var embed = <?php echo $embed; ?>;
var apikey = "<?php echo $apikey; ?>";
```

---

## Usage Examples

### Basic Feed Visualization

Display a simple realtime graph:
```
/vis/realtime?feedid=1
```

Display raw data graph with custom options:
```
/vis/rawdata?feedid=5&colour=FF0000&units=kW&dp=2
```

### Multigraph Visualization

Create a multigraph via the control panel at `/vis/list`, then view it:
```
/vis/multigraph?mid=1
```

### Embedded in Dashboard

Full-screen embedded view:
```
/vis/rawdata?feedid=5&embed=1
```

### External Access

Using read-only API key for public sharing:
```
/vis/multigraph?mid=1&embed=1&apikey=abc123def456...
```

### Feed Reference by Tag

Some visualizations support tag:name format (only works with feeds belonging to active session):
```
/vis/rawdata?feedid=tag:temperature
```

---

## Edition Facilities

The `editor` visualization type permits editing of feed data:

- **EditDaily**: For editing PhpTimeSeries feeds (daily data)
- **EditRealtime**: For editing PhpFina feeds (realtime data)

**Important**: To achieve the edition function, these visualizations must be run in full screen mode (`embed=1`).

---

## Psychrometric Diagrams

Psychrometric diagrams are a way to appreciate the thermal comfort of a house. All you need is a simple sensor of temperature and relative humidity. The principle is to represent the absolute humidity as a function of temperature.

The graph is initially populated by 10 curves of iso relative humidity, represented in light gray. The lower curve corresponds to a relative humidity of 10%, the upper one to a relative humidity of 100%.

We then materialize the zones where it's not good to be:

- the too dry part (green) is the one below the curve of 40% iso humidity curve.
- the parts that are too humid (red/orange) are between the saturation curve (100%) and the 80% iso humidity curve, in which the risks of development of moisture and fungi are high. The graph is ready to receive the measured values. The calculation of absolute humidity for a pressure of 101325 pa (the atmospheric pressure at sea level) is relatively [simple](https://github.com/emoncms/emoncms/blob/master/Modules/vis/visualisations/psychrograph.php#L146).

From a practical point of view, you just have to build multigraphs in emoncms and use them in the psychrometric visualization to appreciate the comfort level of your home.

![creating a multigraph](images/multigraph.png)

![using the psychrograph](images/psychrograph.png)

More details on [1](https://sustainabilityworkshop.venturewell.org/node/1195.html) and [2](https://sustainabilityworkshop.venturewell.org/node/1195.html)

---

## Developer Notes

### Adding a New Visualization Type

1. **Define in vis_object.php**: Add entry to `$visualisations` array with options
2. **Create visualization file**: Add PHP file in `visualisations/` directory
3. **Implement rendering**: Follow pattern of existing visualizations
4. **Handle parameters**: Extract and validate URL parameters
5. **Fetch and render data**: Use Feed API and Flot.js to display graph

### Parameter Validation

The controller automatically validates parameters based on type definitions in `vis_object.php`. Custom validation can be added in the controller's parameter processing loop.

### Authentication

- Feeds must belong to the requesting user OR be marked as public
- Multigraphs must belong to the requesting user
- API keys can be used for external access (read-only)
- Write operations require `$session['write']` permission

---

## Module Responsibilities

The vis module is responsible for:

- Routing visualization requests
- Validating feed and multigraph access
- Rendering visualization templates
- Managing multigraph CRUD operations
- Providing visualization configuration interface
- Supporting embedded and standalone views
- Handling external API access via read-only keys

The module does NOT handle:
- Feed data storage (handled by feed module)
- Input processing (handled by input module)
- User authentication (handled by core emoncms)
- Dashboard layout (handled by dashboard module)
