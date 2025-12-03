<?php
/*
 All Emoncms code is released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.

 ---------------------------------------------------------------------
 Emoncms - open source energy visualisation
 Part of the OpenEnergyMonitor project:
 http://openenergymonitor.org
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class InputMethods
{
    private $mysqli;
    private $redis;
    private $user;
    private $input;
    private $feed;
    private $process;
    private $device;

    public function __construct($mysqli,$redis,$user,$input,$feed,$process,$device)
    {
        $this->mysqli = $mysqli;
        $this->redis = $redis;

        $this->user = $user;
        $this->input = $input;
        $this->feed = $feed;
        $this->process = $process;
        $this->device = $device;
    }

    // ------------------------------------------------------------------------------------
    // input/post method
    //
    // input/post.json?node=10&json={power1:100,power2:200,power3:300}
    // input/post.json?node=10&csv=100,200,300
    // ------------------------------------------------------------------------------------
    public function post($userid)
    {
        global $route,$param;

        $nodeid = 0;
        if ($route->subaction) {
            $nodeid = $route->subaction;
        } elseif ($param->exists('node')) {
            $nodeid = $param->val('node');
        }

        $payload = $this->parse_payload($param);
        if (isset($payload['error'])) return $payload['error'];

        if ($param->exists('time')) {
            $time = $this->parse_time($param->val('time'));
        } elseif (isset($payload['time'])) {
            $time = $this->parse_time($payload['time']);
        } else {
            $time = time();
        }

        $result = $this->process_node($userid,$time,$nodeid,$payload['inputs']);
        if ($result!==true) return $result;

        return "ok";
    }

    /*

    input/bulk.json?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]

    The first number of each node is the time offset (see below).

    The second number is the node id, this is the unique identifer for the wireless node.

    All the numbers after the first two are data values. The first node here (node 16) has only one data value: 1137.

    Optional offset and time parameters allow the sender to set the time
    reference for the packets.
    If none is specified, it is assumed that the last packet just arrived.
    The time for the other packets is then calculated accordingly.

    offset=-10 means the time of each packet is relative to [now -10 s].
    time=1387730127 means the time of each packet is relative to 1387730127
    (number of seconds since 1970-01-01 00:00:00 UTC)

    Examples:

    // legacy mode: 4 is 0, 2 is -2 and 0 is -4 seconds to now.
      input/bulk.json?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]
    // offset mode: -6 is -16 seconds to now.
      input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10
    // time mode: -6 is 1387730121
      input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=1387730127
    // sentat (sent at) mode:
      input/bulk.json?data=[[520,16,1137],[530,17,1437,3164],[535,19,1412,3077]]&offset=543

    See pull request for full discussion:
    https://github.com/emoncms/emoncms/pull/118
    */
    public function bulk($userid)
    {
        global $param;

        $data = $param->val('data');

        if ($param->exists('cb')) {
            // data is compressed binary format
            $data = file_get_contents('php://input');
            $data = @gzuncompress($data);
        } elseif ($param->exists('c')) {
            // data is compressed hex format
            $bindata = hex2bin($data);
            if (!$bindata) {
                return "Format error, compressed hex not valid";
            }
            $data = @gzuncompress($bindata);
        }
        if ($data===null) return "Format error, json string supplied is not valid";
        $data = json_decode($data);
        if (!is_array($data) || count($data)==0) return "Format error, json string supplied is not valid";

        $len = count($data);
        if (!isset($data[$len-1][0])) return "Format error, last item in bulk data does not contain any data";

        $time_ref = time() - (int) $data[$len-1][0];

        // Sent at mode: input/bulk.json?data=[[45,16,1137],[50,17,1437,3164],[55,19,1412,3077]]&sentat=60
        if ($param->exists('sentat')) {
            $time_ref = time() - (int) $param->val('sentat');
        }
        // Offset mode: input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10
        elseif ($param->exists('offset')) {
            $time_ref = time() - (int) $param->val('offset');
        }
        // Time mode: input/bulk.json?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=1387729425
        elseif ($param->exists('time')) {
            $parsed = $this->parse_time($param->val('time'), false);
            if ($parsed === null) return "Format error, time parameter not valid";
            $time_ref = $parsed;
        }

        $packets = array();
        foreach ($data as $item)
        {
            if (!is_array($item) || count($item)<3) {
                continue;
            }

            $packet_time = isset($item[0]) ? (int) $item[0] : 0;
            if (!is_object($item[1])) {
                $nodeid = $item[1];
            } else {
                return "Format error, node must not be an object";
            }
            if ($nodeid=="") $nodeid = 0;

            $inputs = array();
            $name = 1;
            for ($i=2; $i<count($item); $i++)
            {
                $value = $item[$i];
                if (is_object($value))
                {
                    foreach ($value as $key=>$val) {
                        $inputs[$key] = (float) $val;
                    }
                    continue;
                }
                if ($value===null || strlen($value))
                {
                    $inputs[$name] = (float) $value;
                }
                $name ++;
            }

            $packets[] = array(
                'time' => $time_ref + $packet_time,
                'nodeid' => $nodeid,
                'inputs' => $inputs
            );
        }

        foreach ($packets as $packet) {
            $result = $this->process_node($userid,$packet['time'],$packet['nodeid'],$packet['inputs']);
            if ($result!==true) return $result;
        }

        return "ok";
    }

    // ------------------------------------------------------------------------------------
    // Register and process the inputs for the node given
    // This function is used by all input methods
    // ------------------------------------------------------------------------------------
    public function process_node($userid,$time,$nodeid,$inputs)
    {
        $dbinputs = $this->input->get_inputs($userid);
        $nodeid = preg_replace('/[^\p{N}\p{L}_\s\-.]/u','',$nodeid);
        if ($nodeid=="") $nodeid = 0;

        $validate = $this->input->validate_access($dbinputs, $nodeid);
        if (!$validate['success']) return "Error: ".$validate['message'];

        $this->ensure_node_and_device($userid, $nodeid, $dbinputs);
        $this->update_device_ip($userid, $nodeid);
        $this->process_inputs($userid, $time, $nodeid, $inputs, $dbinputs);

        return true;
    }

    private function parse_time($inputtime, $fallbackNow = true)
    {
        if ($inputtime === null) {
            return $fallbackNow ? time() : null;
        }

        if (is_numeric($inputtime)) {
            return (int) $inputtime;
        }

        if (is_string($inputtime)) {
            $timestamp = strtotime($inputtime);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return $fallbackNow ? time() : null;
    }

    /**
     * Parse incoming input data for input/post & input/bulk.
     *
     * Supported formats:
     *   - fulljson=  → STRICT JSON (modern)
     *   - json=      → Legacy flexible JSON-like "key:value" format
     *   - csv=       → CSV values, optionally with key:value format
     *   - data=      → Alias for csv=
     *
     * This version preserves backward compatibility while supporting
     * strict JSON when required.
     *
     * @param  object $param   Parameter object from EmonCMS framework
     * @return array           ['inputs'=>..., 'time'=>...] OR ['error'=>msg]
     */
    private function parse_payload($param)
    {
        /* ----------------------------------------------------------
        0. VALIDATE presence of any input data
        ---------------------------------------------------------- */
        $hasData = $param->exists('json') ||
                $param->exists('fulljson') ||
                $param->exists('csv') ||
                $param->exists('data');

        if (!$hasData) {
            return ['error' => "Request contains no data via csv, json, fulljson or data"];
        }


        /* ==========================================================
        1. STRICT JSON MODE  (fulljson=)
        ----------------------------------------------------------
        - Required to be valid JSON
        - Used by modern integrations (Node-RED, Python, apps)
        - Legacy tolerance NOT applied here
        ========================================================== */
        if ($param->exists('fulljson')) {

            $datain = $param->val('fulljson');
            if ($datain === "") {
                return ['error' => "fulljson parameter provided but empty"];
            }

            // STRICT JSON decode
            $jsondata = json_decode($datain, true, 2);
            if (!is_array($jsondata)) {
                return ['error' => "fulljson must be valid JSON"];
            }

            // Extract optional "time" key
            $time = null;
            foreach ($jsondata as $key => $value) {
                if (strcasecmp($key, 'time') === 0) {
                    $time = $value;
                    unset($jsondata[$key]);
                }
            }

            return ['inputs' => $jsondata, 'time' => $time];
        }


        /* ==========================================================
        2. LEGACY JSON MODE  (json=)
        ----------------------------------------------------------
        - Original EmonCMS behaviour: tolerant parsing
        - Supports:  json={a:100,b:200}
        - Falls back to KEY:VAL parsing if strict JSON fails
        - Very important for backward compatibility
        ========================================================== */
        if ($param->exists('json')) {

            $datain = trim($param->val('json'));
            if ($datain === "") {
                return ['error' => "json parameter provided but empty"];
            }

            /* ------------------------------------------
            2A. Attempt strict JSON first
            ------------------------------------------ */
            $jsondata = json_decode($datain, true);
            if (is_array($jsondata)) {

                // Extract optional "time"
                $time = null;
                foreach ($jsondata as $key => $value) {
                    if (strcasecmp($key, 'time') === 0) {
                        $time = $value;
                        unset($jsondata[$key]);
                    }
                }

                return ['inputs' => $jsondata, 'time' => $time];
            }


            /* ------------------------------------------
            2B. STRICT JSON FAILED → legacy fallback
            ------------------------------------------ */

            // Remove braces { }
            if ($datain[0] === '{' && substr($datain, -1) === '}') {
                $datain = substr($datain, 1, -1);
            }

            // Soft sanitization (preserve valid chars)
            $clean = preg_replace('/[^\w\s\-.:,]/u', '', $datain);

            $pairs = explode(',', $clean);

            $inputs = [];
            $time   = null;

            foreach ($pairs as $pair) {

                $kv = explode(':', $pair);

                if (!isset($kv[1])) {
                    return ['error' => "Legacy JSON format error: expected key:value"];
                }

                $key = trim($kv[0]);
                $val = trim($kv[1]);

                if ($key === "") {
                    return ['error' => "Legacy JSON key is empty or invalid"];
                }

                // Special handling for "time"
                if (strcasecmp($key, 'time') === 0) {
                    if (!is_numeric($val)) {
                        return ['error' => "Time value must be numeric"];
                    }
                    $time = (int)$val;
                    continue;
                }

                // All numeric or null allowed
                if (!is_numeric($val) && $val !== "null") {
                    return ['error' => "Legacy JSON value for '$key' must be numeric"];
                }

                // Store
                $inputs[$key] = ($val === "null") ? null : (float)$val;
            }

            return ['inputs' => $inputs, 'time' => $time];
        }



        /* ==========================================================
        3. CSV or DATA  (csv= or data=)
        ----------------------------------------------------------
        - Supports formats like:
                csv=100,200,300
                data=1:100,2:150
        - Legacy EmonCMS behaviour unchanged
        ========================================================== */
        $datain = $param->val($param->exists('csv') ? 'csv' : 'data');

        if ($datain === "") {
            return ['error' => "csv/data parameter provided but empty"];
        }

        // Keep same sanitization rules as original EmonCMS
        $clean = preg_replace('/[^\p{N}\p{L}_\s\-.:,]/u', '', $datain);
        $pairs = explode(',', $clean);

        $inputs = [];
        $index = 0;

        foreach ($pairs as $pair) {

            $kv = explode(':', $pair);

            /* ------------------------------------------
            KEY:VALUE CSV FORMAT
            ------------------------------------------ */
            if (isset($kv[1])) {

                if ($kv[0] === "") {
                    return ['error' => "CSV key is empty"];
                }
                if (!is_numeric($kv[1]) && $kv[1] !== "null") {
                    return ['error' => "CSV value for key '{$kv[0]}' must be numeric"];
                }

                $inputs[$kv[0]] = ($kv[1] === "null") ? null : (float)$kv[1];
            }

            /* ------------------------------------------
            PURE CSV FORMAT (no keys)
            ------------------------------------------ */
            else {

                if (!is_numeric($kv[0]) && $kv[0] !== "null") {
                    return ['error' => "CSV value must be numeric"];
                }

                $inputs[$index + 1] = ($kv[0] === "null") ? null : (float)$kv[0];
                $index++;
            }
        }

        return ['inputs' => $inputs];
    }

    private function ensure_node_and_device($userid, $nodeid, &$dbinputs)
    {
        if (!isset($dbinputs[$nodeid])) {
            $dbinputs[$nodeid] = array();
            if ($this->device) $this->device->create($userid, $nodeid, null, null, null);
        }
    }

    private function update_device_ip($userid, $nodeid)
    {
        if (!$this->device) return;

        $deviceid = $this->device->exists_nodeid($userid, $nodeid);
        if ($deviceid) {
            $ip = get_client_ip_env();
            $this->device->set_fields($deviceid, json_encode(array('ip' => $ip)));
        }
    }

    private function process_inputs($userid, $time, $nodeid, $inputs, &$dbinputs)
    {
        $to_process = array();
        foreach ($inputs as $name => $value) {
            $name = preg_replace('/[^\p{N}\p{L}_\s\-.]/u','',$name);
            if (!isset($dbinputs[$nodeid][$name])) {
                $inputid = $this->input->create_input($userid, $nodeid, $name);
                $dbinputs[$nodeid][$name] = array('id' => $inputid, 'processList' => '');
            }
            $inputid = $dbinputs[$nodeid][$name]['id'];
            $this->input->set_timevalue($inputid, $time, $value);
            if ($dbinputs[$nodeid][$name]['processList']) {
                $to_process[] = array(
                    'value' => $value,
                    'processList' => $dbinputs[$nodeid][$name]['processList'],
                    'opt' => array('sourcetype' => ProcessOriginType::INPUT, 'sourceid' => $inputid)
                );
            }
            if (isset($_GET['mqttpub'])) $this->process->publish_to_mqtt("emon/$nodeid/$name",$time,$value);
        }
        foreach ($to_process as $i) $this->process->input($time, $i['value'], $i['processList'], $i['opt']);
    }

}
