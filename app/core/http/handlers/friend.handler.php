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
require_once "./app/core/http/api.push.server.php";

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
                              UNION
                              SELECT fid FROM friends
                              WHERE (Entity_Id1 = :sender AND Entity_Id2 = :receiver)
                              OR    (Entity_Id2 = :sender AND Entity_Id1 = :receiver)
                          )
                          LIMIT 1
                          ";

        // Bind the parameters to the query
        $data = Array(":sender" => $user['entityId'], ":receiver" => $friendId);

        // Perform the insert, then increment count if this wasn't a duplicate record
        if (Database::getInstance()->insert($query, $data) != 0) {

            $query = "SELECT Entity_Id, First_Name, Last_Name
                      FROM entity
                      WHERE Entity_Id = :friendId ";

            $data = Array("friendId" => $friendId);

            // This expression will result to std::object or false - this is why we perform a boolean check
            $friend = Database::getInstance()->fetch($query, $data);
            if ($friend) {

                // Today's date and time.
                $now = gmdate('Y-m-d H:i:s', time());

                // Configure the push payload, we trim the name so that if it was Alexander John, it becomes Alexander.
                $pushPayload = Array(
                    "senderId" => $user['entityId'],
                    "senderName" => $user['firstName'] . " " . $user['lastName'],
                    "receiver" => $friendId,
                    "message" => $user['firstName']. " " . $user['lastName'] . " wants to add you as a friend.",
                    "type" => 3,
                    "date" => $now,
                    "messageId" => NULL,
                    "messageType" => NULL
                );

                // Reference a new push server and send the notification.
                $server = new \PushServer();

                $res = $server->sendNotification($pushPayload);

                if(!empty($res))
                    // Request and notification (push) sent.
                    return Array("error" => "200", "message" => "The friend request to ".$friend->First_Name." ".$friend->Last_Name. " ".
                        "has been sent successfully.", "params" => "");

                else
                    // Only request sent.
                    return Array("error" => "207", "message" => "Partial success: A friend request has been sent, but without a notification",
                                 "payload" => "");
            }
            else
                // Friend cannot be found.
                return Array("error" => "400", "message" => "The identifier for the friend cannot be found. Please send".
                             "a request to a established user. ", "payload" => "");

        }
        else
            // Conflict with either friends or friend_requests table.
            return Array("error" => "409", "message" => "You are either already friends with this user, or a friend request has already"
                                                       ." been sent." , "params" => "");

    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ACCEPT FRIEND REQUEST
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function acceptFriendRequest($friendId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // First we need to delete the friend request. If this check fails, then we know that the friend request does
        // not exist in the table. This is better than doing a select and a delete query.
        // The identifier of the 'friend' in this case, will be the sender, as they were the one who sent the request
        // in the first place.
        $query = "DELETE FROM friend_requests
                  WHERE Req_Sender = :friendId
                  AND Req_Receiver = :userId ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        if(Database::getInstance()->delete($query, $data)) {

            // Prepare the query for adding a row into the friends table.
            $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category)
                      VALUES              (:userId, :friendId, 2) ";

            // Bind the parameters to the query
            $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

            // Perform the insert, then increment count if this wasn't a duplicate record
            if (Database::getInstance()->insert($query, $data)) {

                $query = "SELECT Entity_Id, First_Name, Last_Name
                          FROM entity
                          WHERE Entity_Id = :friendId ";

                $data = Array("friendId" => $friendId);

                // This expression will result to std::object or false - this is why we perform a boolean check
                $friend = Database::getInstance()->fetch($query, $data);

                // Retrieve the friends name to make the return message more useful.
                if ($friend) {

                    return Array("error" => "200", "message" => "" . $friend->First_Name . " " . $friend->Last_Name . " has been added to your " .
                        "friends list.", "payload" => "");
                } else
                    // Retrieving friends first/last name has failed.
                    return Array("error" => "400", "message" => "Error retrieving friend from the database.", "payload" => "");
            } else
                // Inserting a new row into the friends table has failed.
                return Array("error" => "400", "message" => "Error adding friend. The query has failed.", "payload" => "");
        }
        else
            // Deleting the friend-request has failed.
            return Array("error" => "400", "message" => "The friend request does not exist.", "payload" => "");
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
     | Note: when testing, if you send a friend request then delete, it will not be removed because it
     | will be in the friend-requests table.
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
    | (DELETE) REJECT FRIEND REQUEST
    |--------------------------------------------------------------------------
    |
    | Rejects a friend request.
    | The userId will be the reciever for the request, and the friendId will be the
    | sender. This effectively removes the request row from the table.
    |
    | @param $friendId - The identifer of the user to reject.
    |
    | @param $payload  - ALl of the Curl JSON information.
    |
    | @return          A success or failure return array depending on the outcome.
    |
    */
    public static function rejectFriendRequest($friendId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");


        // Prepare a query that's purpose will be to delete all records between a user and a current friend.
        $query = "DELETE FROM friend_requests WHERE Req_Sender = :friendId AND Req_Receiver = :userId ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "Friend request has been removed.", "params" => "");
        else
            return Array("error" => "400", "message" => "Friend request does not exist, so it cannot be removed."
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

        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "This user has been blocked successfully.", "params" => "");
        else
            return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist."
            , "params" => "");
    }

}