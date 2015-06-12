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
     | Sends a new friend request to a user.
     |
     | @param $userId   - The ID for this user.
     |
     | @param $friendID - The ID of the users friend.
     |
     | @param $payload  - ALl of the Curl JSON information.
     |
     | @return a success or failure message based on the result of the query.
     |
     */

    public static function sendFriendRequest($friendId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // Prepare a query that's purpose will be to insert a new friend request into the friend_requests table.
        $query = "INSERT INTO friend_requests(Req_Sender, Req_Receiver)
                          SELECT :sender, :receiver
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT Req_Id FROM friend_requests
                              WHERE (Req_Sender = :sender AND Req_Receiver = :receiver)
                              OR    (Req_Receiver = :receiver AND Req_Sender = :sender)
                          )
                          LIMIT 1
                          ";

        // Bind the parameters to the query
        $data = Array(":sender" => $user['entityId'], ":receiver" => $friendId);

        // Perform the insert, then increment count if this wasn't a duplicate record
        if (Database::getInstance()->insert($query, $data) != 0)
            return Array("error" => "200", "message" => "Friend request has been sent successfully.", "params" => "");
        else
            return Array("error" => "409", "message" => "Conflict: A friend request has already been sent to this user."
                , "params" => "");

        // TODO: Send a push notification (change the above to reflect this change).
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
     | Removes a friend. It is presumed that the friend will be deleted from all categories.
     |
     | @param $friendId - The identifer of the friend to remove.
     |
     | @param $payload  - ALl of the Curl JSON information.
     |
     | @return          A success or failure return array depending on the outcome.
     |
     */

    public static function removeFriend($friendId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // Prepare a query that's purpose will be to delete all records between a user and a current friend.
        $query = "DELETE FROM friends WHERE Entity_Id1 = :userId AND Entity_Id2 = :friendId ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "Friend has been removed successfully from all categories.", "params" => "");
        else
            return Array("error" => "409", "message" => "Conflict: The user and friend id specified have no relationship with "
                            ."one another."
            , "params" => "");

    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) BLOCK USER
     |--------------------------------------------------------------------------
     |
     | Change the category of a friendship between two people in order to block
     | the lines of communication. Simply updates all rows that pertain to the two
     | user identifiers.
     |
     | @param userId   - The entityId. Sent as a header instead of as a part of a payload
     |                   that is authenticated because the PUT verb was used.
     |
     | @param friendId - The identifier of the 'friend'.
     |
     |
     */

    public static function blockUser($userId, $friendId)
    {
        // Prepare a query that's purpose will be to update the friends table to change the
        // category of a relationship between two users to 4.
        // However, this should only be done in one direction rather than two.
        // For example: user blocks friend, but the query must not do it the other way around too.
        // So...more basic queries for that.
        $query = "UPDATE friends
                  SET Category = 4
                  WHERE Entity_Id1 = :userId AND Entity_Id2 = :friendId ";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "This user has been blocked successfully.", "params" => "");
        else
            return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist."
            , "params" => "");
    }

}