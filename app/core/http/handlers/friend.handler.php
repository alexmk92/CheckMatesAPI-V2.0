<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Friend Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Friend handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler object
require_once "./app/core/http/handlers/session.handler.php";

class Friend
{

    /*
    |--------------------------------------------------------------------------
    | Get Friends
    |--------------------------------------------------------------------------
    |
    | Returns an array of all friends that the user has.
    |
    | @param $userId - The ID for this user (must be Kinekt ID) however it will
    |                  check for entityID (this is for legacy code which used
    |                  the entityID instead...) the UNION will solve the problem
    |                  of duplicate users in the even that the two keys ever
    |                  conflicted.
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function getFriends($userId, $filter = NULL)
    {
        $data = Array(":entity_id" => $userId);
        if(!empty($filter))
            $data[":tagged"] = $filter["tagged"];
        else
            $data[":tagged"] = "";

        // NOTE* I would like to tailor this to return the specific fields we need,
        // for now im not sure if this will suffice -  shout if more is needed
        $query = "SELECT DISTINCT entity_id, fb_id, first_name, last_name, profile_pic_url, last_checkin_place, category
                  FROM entity
                  JOIN friends
                  ON entity.entity_id = friends.entity_id2 OR entity.entity_id = friends.entity_id1
                  WHERE entity_id IN
                  (
                    SELECT entity_id1 FROM friends WHERE entity_id2 = :entity_id AND Category != 4
                    UNION ALL
                    SELECT entity_id2 FROM friends WHERE entity_id1 = :entity_id AND Category != 4
                  )
                  AND entity_id NOT IN( :tagged )
                  ";

        // Check we recieved a valid object back to set the response.
        $res = Database::getInstance()->fetchAll($query, $data);
        $count = sizeof($res);

        // Build the payload - append any HATEOS info here
        $payload = Array();
        foreach ($res as $user)
        {
            $user["uri_info"] = Array(
                "user" => "/User/{$user["fb_id"]}",
                "messages" => "/Messages/{$user["fb_id"]}"
            );
            array_push($payload, $user);
        }

        // Account for an invalid request
        if($count == 0)
            return Array('error' => '404', 'message' => 'This user has no friends.');

        // Format the response object.
        return Array('error' => '200', 'message' => "Found {$count} friends for this user.", 'payload' => $payload);

    }

    /*
    |--------------------------------------------------------------------------
    | Get Friends Requests
    |--------------------------------------------------------------------------
    |
    | Returns an array of all friend requests for this user.
    |
    | @param $userId - The ID for this user (must be kinekt ID)
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function getFriendRequests($userId)
    {
        $DB = Database::getInstance();

        $data = Array(":entity_id" => $userId);
        $query = "SELECT * FROM friend_requests WHERE req_receiver = :entity_id";

        $requests = $DB->fetchAll($query, $data);
        return Array("error" => "200", "message" => "Successfully returned friends for this user", "payload" => $requests);
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) SEND FRIEND REQUEST
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function sendFriendRequest($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ACCEPT FRIEND REQUEST
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function acceptFriendRequest($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
    |--------------------------------------------------------------------------
    | (POST) Add Friends
    |--------------------------------------------------------------------------
    |
    | Adds the friends in the given array to the database for this user, the
    | id passed to this method will be the facebook id of the list of users
    | as well as the facebook_id of the current user.
    |
    | @param $friends  - An array of Facebook users
    | @param $category - The category for this relation - 1 = FB, 2 = Kinekt 3 = ALL 4 = Blocked
    | @param $userId   - The ID for this user (must be facebook ID)
    |
    */

    public static function addFriends($friends, $category, $userId)
    {
        // Create an array of unique friends
        $friends = array_filter(array_unique(explode(",", $friends)));

        // Set variables for response - iCount = insertCount, aCount = noAccountCount and fCount = friendsCount
        $iCount = 0;
        $aCount = 0;
        $fCount = sizeof($friends);

        // Ensure that we have sent some users to add, if not then return an error 400
        if($iCount == $fCount)
            return Array('error' => '400', 'message' => 'Bad request, no users were sent in the POST body.');

        // Loop through the friends array and insert each unique user
        foreach ($friends as $friend)
        {
            $query = "SELECT entity_id FROM entity WHERE fb_id = :fb_id";
            $data  = Array(":fb_id" => $friend);

            $entId = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);
            if($entId["entity_id"] != 0) {
                $friend = $entId["entity_id"];

                // Checks to see whether the user combination already exists in the database, uses
                // DUAL for the initial select to ensure we fetch from cache if the friends table is empty.
                $query = "INSERT INTO friends(entity_id1, entity_id2, category)
                          SELECT :entityA, :entityB, :category
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT fid FROM friends
                              WHERE (entity_id1 = :entityA AND entity_id2 = :entityB)
                              OR    (entity_id1 = :entityB AND entity_id2 = :entityA)
                          )
                          LIMIT 1
                          ";

                // Bind the parameters to the query
                $data = Array(":entityA" => $userId, ":entityB" => $friend, ":category" => $category);

                // Perform the insert, then increment count if this wasn't a duplicate record
                if (Database::getInstance()->insert($query, $data) != 0)
                    $iCount++;
            }
            else
            {
                $aCount++;
            }
        }

        // Everything was successful, print out the response
        $diff = ($fCount - $iCount) - $aCount;
        $msg  = ($iCount == 0) ? "Oops, no users were added:" : "Success, the users were added:";
        return Array('error' => '200', 'message' => "{$msg} out of {$fCount} friends, {$iCount} were inserted, {$diff} were duplicates and {$aCount} of your friends does not have a Kinekt account.");
    }

     /*
     |--------------------------------------------------------------------------
     | DELETE FRIEND
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function removeFriend($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) BLOCK USER
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function blockUser($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

}