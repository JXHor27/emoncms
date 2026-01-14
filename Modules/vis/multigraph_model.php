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

/**
 * Multigraph Model Class
 * 
 * Handles database operations for multigraphs - collections of feeds that can be
 * displayed together in visualizations. Multigraphs store feed configurations
 * as JSON strings in the database.
 * 
 * Database Schema:
 * - Table: multigraph
 * - Fields: id (auto_increment), userid, feedlist (TEXT, JSON), name (TEXT)
 * 
 * Dependencies:
 * - Requires mysqli database connection (injected via constructor)
 * - Uses prepared statements for SQL injection protection where applicable
 * 
 * Security:
 * - All user inputs are validated and sanitized
 * - Type casting used for integer parameters
 * - Regex sanitization for string parameters
 * - Ownership checks via userid in WHERE clauses
 * 
 * JSON Handling:
 * - feedlist is stored as JSON string in database
 * - Automatically decoded when retrieved (json_decode)
 * - Should be JSON-encoded when passed to set() method
 */
class Multigraph
{
    /**
     * @var mysqli Database connection object
     */
    private $mysqli;

    /**
     * Constructor
     * 
     * @param mysqli $mysqli Database connection object
     */
    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Creates a new multigraph for a user.
     * 
     * Inserts a new multigraph record with default values:
     * - feedlist: Empty string (will be populated later via set())
     * - name: "New Multigraph" (default name)
     * 
     * @param int $userid User ID who owns the multigraph
     * 
     * @return int|false Multigraph ID on success, false on database error
     *   - Returns mysqli->insert_id (integer) if insert succeeds
     *   - Returns 0 if insert fails (insert_id is 0 on failure)
     *   - Note: Check for > 0 to verify success, as 0 is falsy
     * 
     * Edge Cases:
     * - Invalid userid: Cast to int, may result in 0 (invalid user)
     * - Database error: insert_id returns 0, but no exception thrown
     * - SQL injection: Protected by type casting $userid to int
     * 
     * Example Usage:
     *   $multigraph_id = $multigraph->create($userid);
     *   if ($multigraph_id > 0) {
     *       // Success: use $multigraph_id
     *   } else {
     *       // Failure: handle error
     *   }
     */
    public function create($userid)
    {
        // Type cast to int for SQL injection protection
        $userid = (int) $userid;
        
        // Insert new multigraph with default values
        // Note: Uses direct query (not prepared statement) but $userid is type-cast
        $this->mysqli->query("INSERT INTO multigraph (`userid`,`feedlist`, `name`) VALUES ('$userid','', 'New Multigraph')");
        
        // Return the auto-increment ID of the newly created multigraph
        // Returns 0 if insert failed
        return $this->mysqli->insert_id;
    }

    /**
     * Deletes a multigraph.
     * 
     * Removes a multigraph from the database. Only deletes if the multigraph
     * belongs to the specified user (ownership validation).
     * 
     * @param int $id Multigraph ID to delete
     * @param int $userid User ID for ownership validation
     * 
     * @return array Associative array with operation result:
     *   - Success: array('success' => true, 'message' => 'Multigraph deleted')
     *   - Failure: array('success' => false, 'message' => 'Multigraph was not deleted')
     * 
     * Failure Cases:
     * - Multigraph doesn't exist: affected_rows = 0
     * - Multigraph belongs to different user: affected_rows = 0 (WHERE clause prevents deletion)
     * - Invalid ID: Type cast to 0, no match found
     * 
     * Security:
     * - Uses prepared statements to prevent SQL injection
     * - Ownership check via userid in WHERE clause
     * - Type casting for both parameters
     * 
     * Example Usage:
     *   $result = $multigraph->delete(123, $userid);
     *   if ($result['success']) {
     *       // Deletion successful
     *   } else {
     *       // Handle error: $result['message']
     *   }
     */
    public function delete($id,$userid)
    {
        // Type cast to int for SQL injection protection
        $id = (int) $id;
        $userid = (int) $userid;
        
        // Use prepared statement for security
        $stmt = $this->mysqli->prepare("DELETE FROM multigraph WHERE id=? AND userid=?");
        $stmt->bind_param("ii", $id, $userid);  // Both parameters are integers
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;  // Number of rows actually deleted
        $stmt->close();
        
        // Check if deletion actually occurred
        // affected_rows > 0 means a row was deleted
        // affected_rows = 0 means no matching row (doesn't exist or wrong owner)
        if ($affected_rows>0){
            return array('success'=>true, 'message'=>'Multigraph deleted');
        } else {
            return array('success'=>false, 'message'=>'Multigraph was not deleted');
        }
    }

    /**
     * Updates multigraph name and feedlist configuration.
     * 
     * Modifies an existing multigraph's name and feedlist. The feedlist should
     * be a JSON string containing feed configuration array. Only updates if the
     * multigraph belongs to the specified user (ownership validation).
     * 
     * @param int $id Multigraph ID to update
     * @param int $userid User ID for ownership validation
     * @param string $feedlist JSON string of feed configuration array
     *   Expected format: JSON-encoded array of feed objects
     *   Example: '[{"id":1,"name":"Power","color":"FF0000"},{"id":2,"name":"Temperature"}]'
     * @param string $name Multigraph display name
     * 
     * @return array Associative array with operation result:
     *   - Success: array('success' => true, 'message' => 'Multigraph updated')
     *   - Failure: array('success' => false, 'message' => 'Multigraph was not updated')
     * 
     * Parameter Sanitization:
     * - feedlist: Removes all characters except letters, numbers, spaces, and JSON syntax chars
     *   Allowed: letters, numbers, spaces, dashes, dots, quotes, commas, colons, braces, brackets
     *   Pattern: /[^\p{L}_\p{N}\s\-.",:{}\[\]]/u
     * - name: Removes all characters except letters, numbers, spaces, dashes, dots
     *   Pattern: /[^\p{L}_\p{N}\s\-.]/u
     * 
     * Failure Cases:
     * - Multigraph doesn't exist: affected_rows = 0
     * - Multigraph belongs to different user: affected_rows = 0 (WHERE clause prevents update)
     * - Invalid ID: Type cast to 0, no match found
     * - Invalid feedlist JSON: Sanitization may corrupt JSON structure (validate before calling)
     * 
     * Security:
     * - Uses prepared statements to prevent SQL injection
     * - Ownership check via userid in WHERE clause
     * - Type casting for integer parameters
     * - Regex sanitization for string parameters
     * 
     * Edge Cases:
     * - Empty feedlist string: Valid, creates multigraph with no feeds
     * - Malformed JSON in feedlist: Stored as-is (validation should happen client-side)
     * - Very long feedlist: Database TEXT field has limits
     * 
     * Example Usage:
     *   $feedlist_json = json_encode([['id' => 1, 'name' => 'Power']]);
     *   $result = $multigraph->set(123, $userid, $feedlist_json, 'My Multigraph');
     *   if ($result['success']) {
     *       // Update successful
     *   }
     */
    public function set($id, $userid, $feedlist, $name)
    {
        // Type cast to int for SQL injection protection
        $id = (int) $id;
        $userid = (int) $userid;
        
        // Sanitize feedlist: Remove all characters except letters, numbers, spaces,
        // and JSON syntax characters (quotes, commas, colons, braces, brackets)
        // This allows valid JSON while preventing SQL injection
        $feedlist = preg_replace('/[^\p{L}_\p{N}\s\-.",:{}\[\]]/u','',$feedlist);
        
        // Sanitize name: Remove all characters except letters, numbers, spaces, dashes, dots
        // More restrictive than feedlist since name doesn't need JSON syntax
        $name = preg_replace('/[^\p{L}_\p{N}\s\-.]/u','',$name);

        // Use prepared statement for security
        // Updates both name and feedlist, but only if multigraph belongs to user
        $stmt = $this->mysqli->prepare("UPDATE multigraph SET name=?, feedlist=? WHERE id=? AND userid=?");
        $stmt->bind_param("ssii", $name, $feedlist, $id, $userid);  // s=string, i=integer
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;  // Number of rows actually updated
        $stmt->close();
        
        // Check if update actually occurred
        // affected_rows > 0 means a row was updated
        // affected_rows = 0 means no matching row (doesn't exist or wrong owner)
        if ($affected_rows>0){
            return array('success'=>true, 'message'=>'Multigraph updated');
        } else {
            return array('success'=>false, 'message'=>'Multigraph was not updated');
        }
    }

    /**
     * Retrieves multigraph configuration by ID.
     * 
     * Fetches a multigraph's name and feedlist from the database. The feedlist
     * is stored as a JSON string and is automatically decoded to a PHP array/object.
     * 
     * SECURITY NOTE: This method does NOT check user ownership. It returns the
     * multigraph if it exists, regardless of which user owns it. This is a known
     * security issue that should be addressed in future versions.
     * 
     * TODO: Implement public multigraph feature and ownership check:
     * - Only return feedlist if multigraph is public OR belongs to current user
     * - Add ownership validation similar to delete() and set() methods
     * 
     * @param int $id Multigraph ID to retrieve
     * @param int $userid User ID (currently NOT used for access control - see security note)
     * 
     * @return array|array Associative array with multigraph data:
     *   - Success: array('name' => string, 'feedlist' => array|object)
     *     - 'name': Multigraph display name (string)
     *     - 'feedlist': Decoded JSON feedlist (array or object, depending on JSON structure)
     *   - Failure: array('success' => false, 'message' => 'Multigraph does not exist')
     * 
     * Return Value Structure:
     * - feedlist is decoded from JSON string using json_decode()
     * - If feedlist JSON is empty string "", json_decode returns null
     * - If feedlist JSON is malformed, json_decode returns null
     * - If feedlist JSON is array, returns array
     * - If feedlist JSON is object, returns object (stdClass)
     * 
     * Edge Cases:
     * - Multigraph doesn't exist: Returns error array
     * - Empty feedlist: json_decode('') returns null
     * - Malformed JSON: json_decode() returns null (no error thrown)
     * - Invalid ID: Type cast to 0, query returns no results
     * - No ownership check: Returns multigraph even if belongs to different user (SECURITY ISSUE)
     * 
     * Security:
     * - Type casting for $id prevents SQL injection
     * - WARNING: No user ownership validation (security vulnerability)
     * - Uses direct query (not prepared statement) but $id is type-cast
     * 
     * Example Usage:
     *   $multigraph = $multigraph->get(123, $userid);
     *   if (isset($multigraph['success']) && !$multigraph['success']) {
     *       // Error: $multigraph['message']
     *   } else {
     *       // Success: $multigraph['name'], $multigraph['feedlist']
     *   }
     */
    public function get($id, $userid)
    {
        // Type cast to int for SQL injection protection
        $id = (int) $id;
        $userid = (int) $userid;  // NOTE: Currently not used in query (security issue)
        
        // Query multigraph by ID
        // WARNING: No userid check in WHERE clause - returns multigraph regardless of owner
        $result = $this->mysqli->query("SELECT name, feedlist FROM multigraph WHERE `id`='$id'");
        $result = $result->fetch_array();
        
        // Check if multigraph was found
        if (!$result) {
            return array('success'=>false, 'message'=>'Multigraph does not exist');
        }
        
        // Build return array with decoded feedlist
        $row['name'] = $result['name'];
        
        // Decode JSON string to PHP array/object
        // Returns null if JSON is empty or malformed
        // Returns array if JSON is array, object (stdClass) if JSON is object
        $row['feedlist'] = json_decode($result['feedlist']);
        
        // JSON validation: Check if feedlist is non-empty string that failed to decode
        // Only flag as error if string is not empty and decoding returned null
        // Empty feedlist strings are valid (represents no feeds), so no error
        if (!empty($result['feedlist']) && $row['feedlist'] === null) {
            $row['feedlist_error'] = true;  // Indicates JSON decode failure
        }
        
        return $row;
    }

    /**
     * Retrieves all multigraphs belonging to a user.
     * 
     * Fetches all multigraphs owned by the specified user. Each multigraph's
     * feedlist is automatically decoded from JSON string to PHP array/object.
     * 
     * @param int $userid User ID to retrieve multigraphs for
     * 
     * @return array Array of multigraph objects, each containing:
     *   - 'id' (int): Multigraph ID
     *   - 'name' (string): Multigraph display name
     *   - 'feedlist' (array|object|null): Decoded JSON feedlist
     * 
     * Return Value Structure:
     * - Returns empty array [] if user has no multigraphs
     * - Each multigraph is an associative array
     * - feedlist is decoded from JSON string using json_decode()
     * - If feedlist JSON is empty or malformed, json_decode returns null
     * 
     * Edge Cases:
     * - User has no multigraphs: Returns empty array []
     * - Empty feedlist: json_decode('') returns null for that multigraph
     * - Malformed JSON in feedlist: json_decode() returns null (no error thrown)
     * - Invalid userid: Type cast to 0, query returns no results (empty array)
     * - Database error: fetch_object() may return false, but loop handles gracefully
     * 
     * Security:
     * - Type casting for $userid prevents SQL injection
     * - Ownership check via userid in WHERE clause (only returns user's multigraphs)
     * - Uses direct query (not prepared statement) but $userid is type-cast
     * 
     * Example Usage:
     *   $multigraphs = $multigraph->getlist($userid);
     *   foreach ($multigraphs as $mg) {
     *       echo $mg['name'];  // Multigraph name
     *       print_r($mg['feedlist']);  // Feed configuration array
     *   }
     */
    public function getlist($userid)
    {
        // Type cast to int for SQL injection protection
        $userid = (int) $userid;
        
        // Query all multigraphs for this user
        // Ownership check via userid in WHERE clause
        $result = $this->mysqli->query("SELECT id,name,feedlist FROM multigraph WHERE `userid`='$userid'");

        // Build array of multigraph objects
        $multigraphs = array();
        while ($row = $result->fetch_object())
        {
            // Decode feedlist JSON string to PHP array/object
            // Returns null if JSON is empty or malformed
            $decoded_feedlist = json_decode($row->feedlist);
            
            // Build multigraph entry
            $multigraph_entry = array(
                'id'=>$row->id,
                'name'=>$row->name,
                'feedlist'=>$decoded_feedlist
            );
            
            // JSON validation: Check if feedlist is non-empty string that failed to decode
            // Only flag as error if string is not empty and decoding returned null
            // Empty feedlist strings are valid (represents no feeds), so no error
            if (!empty($row->feedlist) && $decoded_feedlist === null) {
                $multigraph_entry['feedlist_error'] = true;  // Indicates JSON decode failure
            }
            
            $multigraphs[] = $multigraph_entry;
        }
        
        // Returns empty array if user has no multigraphs
        return $multigraphs;
    }

}
