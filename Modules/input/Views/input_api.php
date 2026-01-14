<?php 
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $session, $user; 
?>

<style>
/* Modern API Documentation Styling */
.api-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.api-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.api-header h2 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 600;
}

.api-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1em;
}

.section {
    background: white;
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border: 1px solid #e1e8ed;
}

.section h3 {
    color: #2c3e50;
    font-size: 1.8em;
    margin-top: 0;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 3px solid #667eea;
}

.section h4 {
    color: #34495e;
    font-size: 1.4em;
    margin-top: 30px;
    margin-bottom: 15px;
    padding-left: 12px;
    border-left: 4px solid #667eea;
}

.api-key-box {
    background: #f8f9fa;
    border: 2px solid #667eea;
    border-radius: 8px;
    padding: 20px;
    margin: 15px 0;
}

.api-key-box label {
    display: block;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    font-size: 0.95em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.api-key-box input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    background: white;
    transition: border-color 0.3s;
}

.api-key-box input[type="text"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196F3;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.info-box p {
    margin: 10px 0;
    color: #1565C0;
}

ul.styled-list {
    list-style: none;
    padding-left: 0;
}

ul.styled-list li {
    padding: 10px 0 10px 30px;
    position: relative;
    line-height: 1.6;
}

ul.styled-list li:before {
    content: "→";
    position: absolute;
    left: 0;
    color: #667eea;
    font-weight: bold;
    font-size: 1.2em;
}

.table {
    width: 100%;
    margin: 20px 0;
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 0.95em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 15px 12px;
    border-bottom: 1px solid #e1e8ed;
    vertical-align: top;
}

.table tr:last-child td {
    border-bottom: none;
}

.table tr:hover td {
    background: #f8f9fa;
}

.table td:nth-of-type(1) {
    width: 35%;
    color: #2c3e50;
    font-weight: 500;
}

.table td:nth-of-type(2) {
    width: 8%;
    text-align: center;
    font-weight: 600;
    color: #667eea;
}

.table td:nth-of-type(3) {
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
    word-break: break-all;
}

.table a {
    color: #667eea;
    text-decoration: none;
    transition: color 0.3s;
}

.table a:hover {
    color: #764ba2;
    text-decoration: underline;
}

.table b {
    background: #fff3cd;
    padding: 2px 6px;
    border-radius: 3px;
    color: #856404;
}

.numbered-steps {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px 20px 20px 45px;
    margin: 20px 0;
    line-height: 2;
    counter-reset: step-counter;
    list-style: none;
}

.numbered-steps li {
    counter-increment: step-counter;
    position: relative;
}

.numbered-steps li:before {
    content: counter(step-counter);
    position: absolute;
    left: -30px;
    top: 0;
    background: #667eea;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9em;
}

code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #e83e8c;
}

@media (max-width: 768px) {
    .api-container {
        padding: 10px;
    }
    
    .api-header {
        padding: 30px 20px;
    }
    
    .section {
        padding: 20px 15px;
    }
    
    .table {
        font-size: 0.9em;
    }
    
    .table td {
        padding: 10px 8px;
    }
}
</style>

<div class="api-container">
<div class="api-header">
    <h2><?php echo tr('Input API'); ?></h2>
    <p><?php echo tr('Complete API reference for posting and managing data in EmonCMS'); ?></p>
</div>
<div class="section">
    <h3><?php echo tr('Apikey authentication'); ?></h3>
    <p><?php echo tr('If you want to call any of the following actions when you\'re not logged in, you can authenticate with your API key:'); ?></p>
    <ul class="styled-list">
        <li><?php echo tr('Use POST parameter (Recommended): "apikey=APIKEY"'); ?></li>
        <li><?php echo tr('Add the HTTP header: "Authorization: Bearer APIKEY"'); ?></li>
        <li><?php echo tr('Append on the URL of your request: &apikey=APIKEY'); ?></li>
    </ul>

    <div class="info-box">
        <p><?php echo tr('Alternatively, use the encrypted input method to post data with higher security.'); ?></p>
    </div>

    <div class="api-key-box">
        <label><?php echo tr('Read only:'); ?></label>
        <input type="text" readonly="readonly" value="<?php echo $user->get_apikey_read($session['userid']); ?>" />
    </div>
    
    <div class="api-key-box">
        <label><?php echo tr('Read & Write:'); ?></label>
        <input type="text" readonly="readonly" value="<?php echo $user->get_apikey_write($session['userid']); ?>" />
    </div>
</div>

<div class="section">
    <h3><?php echo tr('Posting data to EmonCMS'); ?></h3>

    <p><?php echo tr('The EmonCMS HTTP input API provides three ways of sending data to EmonCMS:'); ?></p>
    <ul class="styled-list">
        <li><?php echo tr('<b>input/post</b> - Post a single update from a node as either one data item or as a JSON data structure.'); ?></li>
        <li><?php echo tr('<b>input/bulk</b> - Bulk upload historic data from multiple nodes in a single update.'); ?></li>
        <li><?php echo tr('<b>encryption</b> - An encrypted version of both of the above.'); ?></li>
    </ul>

    <div class="info-box">
        <p><?php echo tr("If you're starting out with EmonCMS, 'input/post' is a good starting point for testing. This was emonCMS' original input method. The EmonPi/EmonBase uses the 'input/bulk' input method to post to a remote EmonCMS server as that method provides an option to efficiently upload buffered data after an internet connection outage. Combining multiple updates in a single input/bulk request also reduces bandwidth requirements." ); ?></p>

        <p><?php echo tr("For applications where HTTPS or TLS is not available, EmonCMS offers an in-built transport layer encryption solution where the EmonCMS apikey is used as the pre-shared key for encrypting the data with AES-128-CBC." ); ?></p>
    </div>

    <h4><?php echo tr('input/post'); ?></h4>

    <ul class="styled-list">
        <li><?php echo tr('The <b>fulljson</b> format is recommended for new integrations. It uses the PHP JSON decoder and the answer is also in json.');?></li>
        <li><?php echo tr('The <b>json like</b> format is based on the CSV input parsing implementation and maintained for backward compatibility.'); ?></li>
        <li><?php echo tr('The <b>node</b> parameter can be an unquoted string e.g: emontx or a number e.g: 10.'); ?></li>
        <li><?php echo tr('Time is set as system time unless a <b>time</b> element is included. It can be either a parameter &time (unquoted) or as part of the JSON data structure. If both are included the parameter value will take precedence. Time is a UNIX timestamp and can be in seconds or a string PHP can decode (ISO8061 recommended). If you are having problems, ensure you are using seconds not milliseconds. If part of the JSON data structure is a string, the node value will report NULL'); ?></li>
        <li><?php echo tr('The input/post API is compatible with both GET and POST request methods (POST examples given use curl).'); ?></li>
    </ul>
    
    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('HTTP Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        
        <tr><td><?php echo tr('JSON format'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?node=emontx&fulljson={%22power1%22:100,%22power2%22:200,%22power3%22:300}"><?php echo $path; ?>input/post?<b>node=emontx</b>&fulljson={"power1":100,"power2":200,"power3":300}</a></td></tr>

        <tr><td><?php echo tr('JSON format - with time (as a string in this example)'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?node=emontx&fulljson={%22power1%22:100,%22power2%22:200,%22power3%22:300,%22time%22:%22<?php echo urlencode(date(DATE_ATOM));?>%22}"><?php echo $path; ?>input/post?<b>node=emontx</b>&fulljson={"power1":100,"power2":200,"power3":300,"time":"<?php echo urlencode(date(DATE_ATOM));?>"}</a></td></tr>
        
        <tr><td><?php echo tr('JSON like format'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?node=emontx&json={power1:100,power2:200,power3:300}"><?php echo $path; ?>input/post?<b>node=emontx</b>&json={power1:100,power2:200,power3:300}</a></td></tr>
        
        <tr><td><?php echo tr('CSV format'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?node=mynode&csv=100,200,300"><?php echo $path; ?>input/post?<b>node=mynode</b>&csv=100,200,300</a></td></tr>
        
        <tr><td><?php echo tr('Set the input entry time manually'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?time=<?php echo time(); ?>&node=1&csv=100,200,300"><?php echo $path; ?>input/post?<b>time=<?php echo time(); ?></b>&node=1&csv=100,200,300</a></td></tr>
        
        <tr><td><?php echo tr('Node name as sub-action'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post/emontx?fulljson={%22power1%22:100,%22power2%22:200,%22power3%22:300}"><?php echo $path; ?>input/post<b>/emontx</b>?fulljson={"power1":100,"power2":200,"power3":300}</a></td></tr>

        <tr><td><?php echo tr('To post data from a remote device you will need to include in the request url your write apikey. This give your device write access to your emoncms account, allowing it to post data.'); ?> <?php echo tr('For example using the first json type request above just add the apikey to the end like this:'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/post?node=emontx&fulljson={%22power1%22:100,%22power2%22:200,%22power3%22:300}&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>"><?php echo $path; ?>input/post?node=emontx&fulljson={"power1":100,"power2":200,"power3":300}<b>&apikey=<?php echo $user->get_apikey_write($session['userid']); ?></b></a></td></tr>

        <tr><td><?php echo tr('JSON format:'); ?></td><td>POST</td><td>curl --data "node=1&data={power1:100,power2:200,power3:300}&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/post"</td></tr>
        <tr><td><?php echo tr('CSV format:'); ?></td><td>POST</td><td>curl --data "node=1&data=100,200,300&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/post"</td></tr>
    </table>

    <h4><?php echo tr('input/bulk'); ?></h4>

    <p><?php echo tr('Efficiently upload multiple updates from multiple nodes.'); ?></p>

    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        
        <tr><td><?php echo tr('Example request:'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]"><?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]</a></td></tr>
    </table>

    <ul class="styled-list">
        <li><?php echo tr('The first number of each node is the time offset (see below).'); ?></li>
        <li><?php echo tr('The second number is the node id. This is the unique identifier for the wireless node.'); ?></li>
        <li><?php echo tr('All the numbers after the first two, are data values. The second node here (node 17) has two data values: 1437 and 3164.'); ?></li>
        <li><?php echo tr('Optional offset and time parameters allow the sender to set the time reference for the packets. If none is specified, it is assumed that the last packet just arrived. The time for the other packets is then calculated accordingly.'); ?></li>
    </ul>

    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        
        <tr><td><?php echo tr('Legacy default format (4 is now, 2 is -2 seconds and 0 is -4 seconds to now):'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]"><?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]</a></td></tr>

        <tr><td><?php echo tr('Time offset format (-6 is -16 seconds to now):'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10"><?php echo $path; ?>input/bulk?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]<b>&offset=-10</b></a></td></tr>

        <tr><td><?php echo tr('Sentat format: (useful for sending as positive increasing time index)'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[520,16,1137],[530,17,1437,3164],[535,19,1412,3077]]&sentat=543"><?php echo $path; ?>input/bulk?data=[[520,16,1137],[530,17,1437,3164],[535,19,1412,3077]]<b>&sentat=543</b></b></a></td></tr>

        <tr><td><?php echo tr('Absolute time format (-6 is 1387730121 seconds since 1970-01-01 00:00:00 UTC))'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=<?php echo time(); ?>"><?php echo $path; ?>input/bulk?data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]<b>&time=<?php echo time(); ?></b></a></td></tr>

        <tr><td><?php echo tr('Named feeds (similar to the main example but updates the keys "data" and "anotherData" for node 19)'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,{%22data%22:1412},{%22anotherData%22:3077}]]"><?php echo $path; ?>input/bulk?data=[[0,16,1137],[2,17,1437,3164],[4,19,{"data":1412},{"anotherData":3077}]]</a></td></tr>
            
        <tr><td><?php echo tr('Legacy format:'); ?></td><td>POST</td><td>curl --data "data=[[0,16,1137],[2,17,1437,3164],[4,19,1412,3077]]&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/bulk"</td></tr>

        <tr><td><?php echo tr('Time offset format:'); ?></td><td>POST</td><td>curl --data "data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&offset=-10&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/bulk"</td></tr>

        <tr><td><?php echo tr('Sentat format:'); ?></td><td>POST</td><td>curl --data "data=[[520,16,1137],[530,17,1437,3164],[535,19,1412,3077]]&sentat=543&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/bulk"</td></tr>

        <tr><td><?php echo tr('Absolute time format:'); ?></td><td>POST</td><td>curl --data "data=[[-10,16,1137],[-8,17,1437,3164],[-6,19,1412,3077]]&time=<?php echo time(); ?>&apikey=<?php echo $user->get_apikey_write($session['userid']); ?>" "<?php echo $path; ?>input/bulk"</td></tr>
    </table>

    <h4><?php echo tr('Encryption'); ?></h4>

    <p><?php echo tr("For applications where HTTPS or TLS is not available, EmonCMS offers an in-built transport layer encryption solution where the emoncms apikey is used as the pre-shared key for encrypting the data with AES-128-CBC." ); ?><br><?php echo tr("There is a PHP example of how to generate an encrypted request here: "); ?><a href="https://github.com/emoncms/emoncms/blob/master/docs/input_encrypted.md" target="_blank">PHP Example source code.</a></p>

    <ol class="numbered-steps">
        <li>Start with a request string conforming with the API options above e.g: node=emontx&data={power1:100,power2:200,power3:300}</li>
        <li>Create an initialization vector.</li>
        <li>Encrypt using AES-128-CBC.</li>
        <li>Create a single string starting with the initialization vector followed by the cipher-text result of the AES-128-CBC encryption.</li>
        <li>Convert to a base64 encoded string.</li>
        <li>Generate a HMAC_HASH of the data string together, using the EmonCMS apikey for authorization.</li>
        <li>Send the encrypted string in the POST body of a request to either input/post or input/bulk with headers properties 'Content-type' and 'Authorization' set as below</li>
        <li>Verify the result. The result is a base64 encoded sha256 hash of the json data string.</li>
    </ol>

    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        <tr><td>Encrypted request format</td><td>GET/POST</td><td>URL: /input/post or /input/bulk<br>HEADER: Authorization: USERID:HMAC_HASH, Content-Type: aes128cbc<br>POST BODY: IV+CIPHERTEXT</td></tr>
    </table>
</div>

<div class="section">
    <h3><?php echo tr('Fetching inputs, updating meta data and other actions'); ?></h3>

    <h4><?php echo tr('Input get'); ?></h4>
    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        <tr><td><?php echo tr('List all nodes and associated inputs:'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/get"><?php echo $path; ?>input/get</a></td></tr>
        <tr><td><?php echo tr('List inputs for specific node:'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/get/emontx"><?php echo $path; ?>input/get/emontx</a></td></tr>
        <tr><td><?php echo tr('Fetch specific input from node:'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/get/emontx/power1"><?php echo $path; ?>input/get/emontx/power1</a></td></tr>
    </table>

    <h4><?php echo tr('Input actions'); ?></h4>
    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        <tr><td><?php echo tr('List of inputs with latest data'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/list"><?php echo $path; ?>input/list</a></td></tr>
        <tr><td><?php echo tr('Get inputs configuration (last time and value not included)'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/getinputs"><?php echo $path; ?>input/getinputs</a></td></tr>
        <tr><td><?php echo tr('Set input fields'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/set?inputid=0&fields={'description':'Input Description'}"><?php echo $path; ?>input/set?inputid=0&fields={'description':'Input Description'}</a></td></tr>
        <tr><td><?php echo tr('Delete an input'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/delete?inputid=0"><?php echo $path; ?>input/delete?inputid=0</a></td></tr>
        <tr><td><?php echo tr('Clean inputs without a process list'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/clean"><?php echo $path; ?>input/clean</a></td></tr>
    </table>

    <h4><?php echo tr('Input process actions'); ?></h4>
    <table class="table">
        <tr><th><?php echo tr('Description'); ?></th><th><?php echo tr('Method'); ?></th><th><?php echo tr('Example'); ?></th></tr>
        <tr><td><?php echo tr('Get input process list'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/process/get?inputid=1"><?php echo $path; ?>input/process/get?inputid=1</a></td></tr>
        <tr><td><?php echo tr('Set input process list'); ?></td><td>POST</td><td><a href="<?php echo $path; ?>input/process/set?inputid=0"><?php echo $path; ?>input/process/set?inputid=0</a> POST body: processlist=[{"fn":"process__log_to_feed","args":[1]}]</td></tr>
        <tr><td><?php echo tr('Reset input process list'); ?></td><td>GET</td><td><a href="<?php echo $path; ?>input/process/reset?inputid=0"><?php echo $path; ?>input/process/reset?inputid=0</a></td></tr>
    </table>
</div>

</div> <!-- end api-container -->
