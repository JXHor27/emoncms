<?php
// test_input_model.php

// Define required constant for Emoncms
define('EMONCMS_EXEC', true);

// 1. ENVIRONMENT SETUP
require "../process_settings.php"; // Load DB credentials from settings.ini
require "../core.php";             // Load helpers
require "../Lib/EmonLogger.php";   // Load logger

// Connect to Database
$mysqli = new mysqli($settings['sql']['server'], $settings['sql']['username'], $settings['sql']['password'], $settings['sql']['database']);
if ($mysqli->connect_error) die("DB Connection Failed: " . $mysqli->connect_error);

// Connect to Redis
$redis = new Redis();
if (!$redis->connect($settings['redis']['host'] ?? 'localhost', $settings['redis']['port'] ?? 6379)) die("Redis Connection Failed");
if (!empty($settings['redis']['prefix'])) $redis->setOption(Redis::OPT_PREFIX, $settings['redis']['prefix']);

// Load the Class Under Test
require "../Modules/input/input_model.php";
$input_model = new Input($mysqli, $redis, $settings);

// --------------------------------------------------------
// TEST HELPERS
// --------------------------------------------------------
function assertTrue($condition, $message) {
    if ($condition) echo "✅ [PASS] $message\n";
    else echo "❌ [FAIL] $message\n";
}

function assertEquals($expected, $actual, $message) {
    if ($expected === $actual) echo "✅ [PASS] $message\n";
    else {
        echo "❌ [FAIL] $message\n";
        echo "   Expected: " . print_r($expected, true) . "\n";
        echo "   Actual:   " . print_r($actual, true) . "\n";
    }
}

// --------------------------------------------------------
// TEST DATA SETUP
// --------------------------------------------------------
$test_userid = 9999; // Use a high ID to avoid clashing with real data
$test_node = "test_node";

echo "\n--- STARTING REGRESSION TESTS ---\n";

// TEARDOWN (Clean start)
$mysqli->query("DELETE FROM input WHERE userid=$test_userid");
$redis->del("user:inputs:$test_userid");
$redis->del("node:inputs:$test_userid:$test_node");
echo "Cleaned up previous test data.\n\n";

// ========================================================
// TEST CASE 1: get_inputs_by_node (and implicit Load)
// ========================================================
echo "TEST CASE 1: Granular Fetching & Caching\n";

// 1. Create Dummy Data in MySQL directly
$stmt = $mysqli->prepare("INSERT INTO input (userid, nodeid, name, processList) VALUES (?, ?, ?, ?)");
$name1 = "sensor_A"; $proc1 = "1:1";
$stmt->bind_param("isss", $test_userid, $test_node, $name1, $proc1);
$stmt->execute();
$id1 = $mysqli->insert_id;

$name2 = "sensor_B"; $proc2 = "";
$stmt->bind_param("isss", $test_userid, $test_node, $name2, $proc2);
$stmt->execute();
$id2 = $mysqli->insert_id;

// 2. Call the new method (This should trigger load_node_to_redis internally)
$result = $input_model->get_inputs_by_node($test_userid, $test_node);

// 3. Assertions
assertTrue(count($result[$test_node]) === 2, "Should return exactly 2 inputs");
assertTrue(isset($result[$test_node]['sensor_A']), "Sensor A should exist");
assertEquals((int)$id1, (int)$result[$test_node]['sensor_A']['id'], "Sensor A ID should match DB");

// 4. Verify Redis Cache (White Box Testing)
// Check if your new secondary index was created
$is_cached = $redis->sIsMember("node:inputs:$test_userid:$test_node", $id1);
assertTrue($is_cached, "Redis Secondary Index (node:inputs) should contain ID $id1");


// ========================================================
// TEST CASE 2: delete() (Single Deletion)
// ========================================================
echo "\nTEST CASE 2: Single Delete & Cache Cleanup\n";

// Action
$res = $input_model->delete($test_userid, $id1);

// Assert Response
assertTrue($res['success'], "Delete method returned success");

// Assert Database
$check = $mysqli->query("SELECT id FROM input WHERE id=$id1");
assertTrue($check->num_rows === 0, "Row $id1 should be gone from MySQL");

// Assert Redis Cleanup
$in_global = $redis->sIsMember("user:inputs:$test_userid", $id1);
$in_node   = $redis->sIsMember("node:inputs:$test_userid:$test_node", $id1);
$data_exists = $redis->exists("input:$id1");

assertTrue(!$in_global, "ID should be removed from Global Redis Set");
assertTrue(!$in_node,   "ID should be removed from Node Redis Set");
assertTrue(!$data_exists, "Input Hash data should be deleted");


// ========================================================
// TEST CASE 3: delete_multiple() (Batch Deletion)
// ========================================================
echo "\nTEST CASE 3: Batch Delete & Cache Cleanup\n";

// Setup: Create 3 new inputs
$ids = [];
for($i=1; $i<=3; $i++) {
    $n = "batch_$i"; $p="";
    $stmt->bind_param("isss", $test_userid, $test_node, $n, $p);
    $stmt->execute();
    $ids[] = $mysqli->insert_id;
    // Pre-load them into Redis to test cleanup
    $redis->hSet("input:".$ids[$i-1], "nodeid", $test_node);
    $redis->sAdd("node:inputs:$test_userid:$test_node", $ids[$i-1]);
}

// Action
$res = $input_model->delete_multiple($test_userid, $ids);

// Assertions
assertTrue($res['success'], "Batch delete returned success");

// Check DB
$ids_str = implode(",", $ids);
$check = $mysqli->query("SELECT id FROM input WHERE id IN ($ids_str)");
assertTrue($check->num_rows === 0, "All batch IDs should be gone from MySQL");

// Check Redis (Check one of them)
$in_node = $redis->sIsMember("node:inputs:$test_userid:$test_node", $ids[0]);
assertTrue(!$in_node, "Batch ID should be cleaned from Redis Node Set");

// ========================================================
// TEST CASE 4: set_timevalue_batch (Scenario: Single Post / 1 Item)
// ========================================================
echo "\nTEST CASE 4: set_timevalue_batch with 1 Item (Simulating Single Post)\n";

// Setup: Create a dummy input (Using your previously created $id1)
// Reset it to 0
$mysqli->query("UPDATE input SET value=0, time=0 WHERE id=$id1");

// Prepare the Payload (Size = 1)
$single_update = [
    ['id' => $id1, 'time' => 1234567890, 'value' => 123.45]
];

// Action
$input_model->set_timevalue_batch($single_update);

// Assert
$res = $mysqli->query("SELECT value, time FROM input WHERE id=$id1");
$row = $res->fetch_assoc();

assertEquals(123.45, (float)$row['value'], "Single Post: Value should be updated");
assertEquals(1234567890, (int)$row['time'], "Single Post: Time should be updated");


// ========================================================
// TEST CASE 5: set_timevalue_batch (Scenario: Bulk Post / Multiple Items)
// ========================================================
echo "\nTEST CASE 5: set_timevalue_batch with Multiple Items (Simulating Bulk)\n";

// Setup: Reset both inputs
$mysqli->query("UPDATE input SET value=0 WHERE id IN ($id1, $id2)");

// Prepare Payload (Size = 2)
$bulk_update = [
    ['id' => $id1, 'time' => 1000, 'value' => 10.5],
    ['id' => $id2, 'time' => 2000, 'value' => 20.5]
];

// Action
$input_model->set_timevalue_batch($bulk_update);

// Assert
$res1 = $mysqli->query("SELECT value FROM input WHERE id=$id1");
$row1 = $res1->fetch_assoc();
$res2 = $mysqli->query("SELECT value FROM input WHERE id=$id2");
$row2 = $res2->fetch_assoc();

assertEquals(10.5, (float)$row1['value'], "Bulk: Item 1 Updated");
assertEquals(20.5, (float)$row2['value'], "Bulk: Item 2 Updated");

echo "\n--- TESTS COMPLETED ---\n";