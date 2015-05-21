<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of generateToken
 *
 * @author admin@3embed
 * 
 * Used to generate different user tokens accross different devices
 * 
 */

require_once './app/core/models/Database.php';

class ManageToken {
    /*
     * variable: db
     * Usage: Holds connection string to database
     *  
     */

    private $db;

    /*
     * variable: sessToken
     * Usage: Holds the generated session token
     */
    private $sess_token;

    /*
     * variable: expiry_in_hrs
     * Usage: Holds the expiry time in hours for a session token
     */
    private $expiry_in_hrs;

    /*
     * variable: user_device_type
     * Usage: Holds the current device type for the user
     */
    private $user_device_type;

    /*
     * variable: enc_char_len
     * Usage: Holds the number of characters that can be used to create a session token
     */
    private $enc_char_len;

    /*
     * variable: dateFormat
     * Usage: Holds the format of the date
     */
    private $dateFormat;

    /*
     * variable: db_session_table
     * Usage: Holds the table name for storing sessions
     */
    private $db_session_table = 'user_sessions';

    /*
     * variable: db_session_table
     * Usage: Holds the table name for storing sessions
     */
    private $db_type_table = 'dev_type';

    /*
     * variable: response_type
     * Usage: Defines the type of output, can be json or xml
     */
    private $response_type = 'json';

    /*
     * Methods in this class
     */

    /*
     * Consturctor for ManageToken
     * 
     * Initializes 
     *      Data base connection for mysql
     *      expiry time in hours (default 48 hours)
     *      encryption character length (default 20 chars)
     */

    public function __construct($expiryHrs = 720, $enc_char_len = 20, $date_format = "Y-m-d H:i:s") {

        $this->db = \Models\Database::getInstance();

        $this->dateFormat = $date_format;

        $this->expiry_in_hrs = $expiryHrs;

        $this->enc_char_len = $enc_char_len;
    }

    /*
     * method name: createSessToken
     * Desc: Genereates session token
     * Input: User Data that need to generate a session token
     * Output: Token generated, expiry date and time in local and GMT
     * 
     * Uses methods: 
     *      generateRandomString() --> Used to generate a random string for token
     *      strtohex() -->   Converts string data to hexa decimal.
     *      getDeviceType() -->   Search db and get the device type id for the provided device name
     */

    public function createSessToken($obj_id, $dev_name, $mac_addr, $push_token) {

        $this->user_device_type = $this->getDeviceType($dev_name);

        $this->sess_token = $this->garbler($mac_addr);

        $curr_time = time();

        $gmt_date = gmdate($this->dateFormat, $curr_time); //Converts date to GMT

        $exp_seconds = $this->expiry_in_hrs * 60 * 60;

        $local_exp_date = date($this->dateFormat, $curr_time + $exp_seconds);

        $gmt_exp_date = gmdate($this->dateFormat, $curr_time + $exp_seconds); //Converts date to GMT by adding expiry date in seconds

        $flag_after_insert = $this->insert_in_db($obj_id, $this->sess_token, $mac_addr, $this->user_device_type, $gmt_date, $gmt_exp_date, $push_token);

        $return_obj = array('Token' => $this->sess_token, 'Expity_local' => $local_exp_date, 'Expity_GMT' => $gmt_exp_date, 'Flag' => $flag_after_insert);

        return $return_obj;
    }

    /*
     * method name: updateSessToken
     * Desc: Genereates new session token and updates in the database
     * Input: User Data that need to generate a session token
     * Output: Token generated, expiry date and time in local and GMT
     * 
     * Uses methods: 
     *      generateRandomString() --> Used to generate a random string for token
     *      strtohex() -->   Converts string data to hexa decimal.
     */

    public function updateSessToken($obj_id, $mac_addr, $push_token) {

        $this->sess_token = $this->garbler($mac_addr);

        $curr_time = time();

        $exp_seconds = $this->expiry_in_hrs * 60 * 60;

        $local_exp_date = date($this->dateFormat, $curr_time + $exp_seconds);

        $gmt_exp_date = gmdate($this->dateFormat, $curr_time + $exp_seconds); //Converts date to GMT by adding expiry date in seconds

        $flag_after_insert = $this->update_in_db($obj_id, $this->sess_token, $mac_addr, $gmt_exp_date, $push_token);

        $return_obj = array('Token' => $this->sess_token, 'Expiry_local' => $local_exp_date, 'Expiry_GMT' => $gmt_exp_date, 'Flag' => $flag_after_insert);

        return $return_obj;
    }

    /*
     * Method name: validateSessToken
     * Desc: Validates a session token for the user
     * Input: Object Id and Token
     * Output: 1 for Success and 0 for Failure
     */

    public function validateSessToken($obj_id, $token) {

        $getTokenQry = "select sid from " . $this->db_session_table . " where oid='" . $obj_id . "' and token = '" . $token . "'";
        $getTokenRes = mysql_query($getTokenQry, $this->db->conn);
        if (mysql_num_rows($getTokenRes) > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    /*
     * Method name: revokeSessToken
     * Desc: Revokes a session token
     * Input: Object Id and Token
     * Output: 1 for Success and 0 for Failure
     */

    public function revokeSessToken($obj_id, $token) {

        $getTokenQry = "delete from " . $this->db_session_table . " where oid='" . $obj_id . "' and token = '" . $token . "'";
        mysql_query($getTokenQry, $this->db->conn);
        if (mysql_affected_rows() > 0) {
            return 1;
        } else {
            return 0;
        }
    }

    /*
     * method name: update_in_db
     * Desc: Inserts created token into the db
     * Input: Object Id, token, device address, device type, Current GMT date, expiry GMT date
     * Output: 1 for success and 0 for error
     * 
     */

    private function update_in_db($obj_id, $token, $mac_addr, $gmt_exp_date, $push_token) {

        if($push_token != '0')
            $push_string = ",push_token = '" . $push_token . "'";

        $query = "UPDATE user_sessions
                  SET token = :token,
                      expiry_gmt = :expiry,
                      loggedIn = 1"


        $updateQry = "update user_sessions set token = '" . $token . "',expiry_gmt = '" . $gmt_exp_date . "',loggedIn = '1' ".$push_string." where oid = " . $obj_id . " and device = '" . $mac_addr . "'";

        mysql_query($updateQry, $this->db->conn);

        var_dump(mysql_affected_rows());
        if (mysql_affected_rows() > 0) {
            return 1;
        } else {
            return 0; //$insertQry;
        }
    }

    /*
     * method name: insert_in_db
     * Desc: Inserts created token into the db
     * Input: Object Id, token, device address, device type, Current GMT date, expiry GMT date
     * Output: 1 for success and 0 for error
     * 
     */

    private function insert_in_db($obj_id, $token, $mac_addr, $type, $gmt_date, $gmt_exp_date, $push_token) {

        $insertQry = "insert into " . $this->db_session_table . "(oid,token,expiry_gmt,device,type,create_date_gmt,push_token) values(
            " . $obj_id . ",
                '" . $token . "',
                    '" . $gmt_exp_date . "',
                        '" . $mac_addr . "',
                            '" . $type . "',
                                '" . $gmt_date . "',
                                    '" . $push_token . "')";

        mysql_query($insertQry, $this->db->conn);
        if (mysql_insert_id() > 0) {
            return 1;
        } else {
            return 0; //$insertQry;
        }
    }

    /*
     * method name: garbler
     * Desc: Garbles the input to generate a unique session token
     * Input: length of the string
     * Output: Random string
     */

    private function garbler($mac_addr) {

        $rand_string = $this->generateRandomString($this->enc_char_len);

        $hex_string = $this->strtohex($mac_addr);

        $our_str = $hex_string . $rand_string;

        $our_str_len = strlen($our_str);

        $rand_num = rand(1, $our_str_len);

        return substr_replace($our_str, $rand_string, $rand_num, 0);
    }

    /*
     * method name: generateRandomString
     * Desc: Generates a random string according to the length of the characters passed
     * Input: length of the string
     * Output: Random string
     */

    private function generateRandomString($length) {

        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }

        return $randomString;
    }

    /*
     * method name: strtohex
     * Desc: Converts string to hexa decimal
     * Input: String data
     * Output: Hexa decimal data
     */

    private function strtohex($input) {

        $output = '';

        foreach (str_split($input) as $c)
            $output.=sprintf("%02X", ord($c));

        return $output;
    }

    /*
     * method name: getDeviceType
     * Desc: Search db and get the device type id for the provided device name
     * Input: Device name
     * Output: Device id if available, else handles error through handleError() method
     * 
     * Uses methods: 
     *      handleError() --> Ouputs the error string and stops the flow
     * 
     */

    private function getDeviceType($dev_name) {

        $getDeviceTypeQry = "select dev_id from " . $this->db_type_table . " where name = '" . $dev_name . "'";

        $getDeviceTypeRes = mysql_query($getDeviceTypeQry, $this->db->conn);

        if (mysql_num_rows($getDeviceTypeRes)) {

            $deviteTypeRow = mysql_fetch_assoc($getDeviceTypeRes);

            return $deviteTypeRow['dev_id'];
        } else {
            $this->handleError('Device type not found');
        }
    }

    /*
     * method name: handleError
     * Desc: Handles errors
     * Input: Error message
     * Output: Error message
     */

    private function handleError($errorMsg) {

        echo $errorMsg;
        return false;
    }

    /*
     * method name: response_json
     * Desc: Handles the response in json format
     * Input: Status number
     * Output: Json array
     */

    private function response($statusNumber, $out_array) {

        if ($this->response_type == 'json') {
            
        } else {
            header("Content-type: text/xml");
            echo $this->response_xml($out_array);
        }
    }

    /*
     * method name: response_xml
     * Desc: Converts array to xml
     * Input: Array data
     * Output: Converted xml tree
     * 
     */

    private function response_xml($array) {
        require_once 'array2xml.php';
        try {
//            header("Content-type: text/xml");
            $xml = new array2xml('my_node');
            $xml->createNode($array);
            return $xml;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /*
     * method name: dateAcTmeZone
     * Desc: Converts date from one time zone to another
     * Input: Current local time, local timezone, time zone to which date should be converted
     * Output: Converted time
     * 
     */

    private function dateAcTmeZone($date_input, $timeZone1, $timeZone2) {

        $date = new DateTime($date_input, new DateTimeZone($timeZone1));

        $date->setTimezone(new DateTimeZone($timeZone2));

        return $date->format('Y-m-d H:i:sP');
    }

}

?>
