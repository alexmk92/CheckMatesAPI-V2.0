<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Session Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Session handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

// Include the token object, this is responsible for secure sessions.
require "./app/core/models/ManageToken.php";

class Session
{
    /*
    |--------------------------------------------------------------------------
    | Set Session
    |--------------------------------------------------------------------------
    |
    | Checks the state of the current users session, if it has not been set
    | a new one is set.  If the old session has expired then another token shall
    | be set to replace the other.
    |
    | Tokens are checked once per session and are managed through the ManageToken
    | class.
    |
    | @param $args - The user object who this session will belong to.
    |
    */

    public static function setSession($entityId, $args)
    {
        // Create a new session token object to handle our session
        $token     = new \ManageToken();

        $pushToken = $args["push_token"];
        $devId     = $args["dev_id"];
        $devType   = $args["device_type"];   // 1 = APPLE, 0 = ANDROID

        // Check if the user already has a session
        $query = "SELECT sid, token, expiry_gmt
                  FROM user_sessions
                  WHERE oid = :entityId
                  AND device = :device";

        $data   = Array("entityId" => $entityId, "device" => $devId);
        $exists = Database::getInstance()->fetch($query, $data);

        // Check if the user has a valid session token, if they don't recreate it.
        if($exists)
        {
            return $token->updateSessToken($entityId, $devId, $pushToken);
        }
        else
        {
            return $token->createSessToken($entityId, $devType, $devId, $pushToken);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Session
    |--------------------------------------------------------------------------
    |
    | Returns all details of the current session, these details are used to
    | check for a valid session to ensure the application remains secure.
    |
    | @param $args - The user object who this session will belong to.
    |
    */

    public static function getSession($token, $deviceId)
    {
        $currGmtDate = gmdate('Y-m-d H:i:s', time());

        // Build the query and to retrieve the session object
        $query = "SELECT us.oid, us.expiry_gmt, us.device, us.type, us.sid,
                         ent.First_Name, ent.Last_Name, ent.Fb_Id, ent.Profile_Pic_Url
                  FROM user_sessions us, entity ent
                  WHERE us.oid = ent.Entity_Id
                  AND us.token = :token
                  AND us.device = :deviceId";

        $data  = Array(":token" => $token, ":deviceId" => $deviceId);

        // Check that we receive session object, otherwise this request is unauthorised
        $res = Database::getInstance()->fetchAll($query, $data);
        if(sizeof($res) != 0)
        {
            if($res[0]["expiry_gmt"] > $currGmtDate) {
                return Array("error" => "200", "message" => "The session in the payload is valid.",
                             "payload" => Array
                                         (
                                                "sid"        => $res[0]["sid"],
                                                "entityId"   => $res[0]["oid"],
                                                "deviceId"   => $res[0]["device"],
                                                "deviceType" => $res[0]["type"],
                                                "firstName"  => $res[0]["First_Name"],
                                                "lastName"   => $res[0]["Last_Name"],
                                                "fbId"       => $res[0]["Fb_Id"],
                                                "profilePic" => $res[0]["Profile_Pic_Url"]
                                         )
                             );
            }
            // The session has expired if we reached here
            return Array("error" => "401", "message" => "The session has expired, please sign in again.");
        }
        else
        {
            // Unauthorised session.
            return Array("error" => "401", "message" => "The token you provided is invalid, you are not authorised to log in.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Session
    |--------------------------------------------------------------------------
    |
    | Validates the session by checking if a session token or device ID were
    | passed, if not the application throws a 400 error.
    |
    | We then check whether we have an expired (463) or unauthorised (401) session
    | in which case an error is echoed back to the client.
    |
    | If a 200 is returned, we retrieve the session details and allow the user
    | to persist using the application.
    |
    | @param $args - The user object who this session will belong to.
    |
    */

    public static function validateSession($sessionToken, $deviceId)
    {
        // Bad request if no info is set
        if($sessionToken == "" || $deviceId == "")
            return Array("error" => "400", "message" => "Bad request, the sessionToken or deviceId were not set.");

        $session = self::getSession($sessionToken, $deviceId);

        // Check for unauthorised or expired session - an expired session could be manually
        // reset, but for now require a login (more secure if a device has been idle for too long...)
        if($session["error"] == "401" || $session["error"] == "463")
            return $session;

        // Everything was fine, send back the payload which details the session object
        if($session["error"] == "200")
        {
            return $session["payload"];
        }
    }

    /*
     * MAY ADD THIS IN FUTURE, REDUNDANT FOR NOW...
     *
    private static function updateActiveTime($entityId)
    {
        $currTime = gmdate("Y-m-d H:i:s", time());

        $query = "UPDATE entity SET last_active_dt_time = :dateTime WHERE entity_id = :entityId";
        $data  = Array(":dateTime" => $currTime, ":entityId" => $entityId);

        $res   = Database::getInstance()->update($query, $data);

        if($res > 0)
            return true;

        return false;
    }
    */
}
