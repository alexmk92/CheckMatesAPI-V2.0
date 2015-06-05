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

        // NOTE* I would like to tailor this to return the specific fields we need,
        // for now im not sure if this will suffice -  shout if more is needed
        $query = "SELECT entity_id, fb_id, first_name, last_name, profile_pic_url, last_checkin_place, category
                  FROM entity
                  JOIN friends
                  ON entity.entity_id = friends.entity_id2 OR entity.entity_id = friends.entity_id1
                  WHERE entity_id IN
                  (
                    SELECT entity_id1 FROM friends WHERE entity_id2 = :entity_id AND Category != 4
                    UNION ALL
                    SELECT entity_id2 FROM friends WHERE entity_id1 = :entity_id AND Category != 4
                  )
                  AND entity_id NOT IN(".$filter["tagged"].")
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

}