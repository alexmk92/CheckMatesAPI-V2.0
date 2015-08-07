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
        $query = "SELECT DISTINCT Entity_Id, Fb_Id, First_Name, Last_Name, Profile_Pic_Url, Last_CheckIn_Place, Category, Sex
                  FROM entity
                  JOIN friends
                  ON entity.Entity_Id = friends.Entity_Id1 OR entity.Entity_Id = friends.Entity_Id2
                  WHERE Entity_Id1 = :entity_id AND Category != 4
                  AND Entity_Id NOT IN( :tagged )
                  AND Entity_Id <> :entity_id
                  GROUP BY Entity_Id
                  ";

        // Check we received a valid object back to set the response.
        $res = Database::getInstance()->fetchAll($query, $data);
        $count = sizeof($res);

        // Build the payload - append any HATEOS info here
        $payload = Array();
        foreach ($res as $user)
        {

            $user["uri_info"] = Array(
                "user" => "/User/{$user["Fb_Id"]}",
                "messages" => "/Messages/{$user["Fb_Id"]}"
            );
            array_push($payload, $user);
        }

        // Account for an invalid request
        if($count == 0)
            return Array('error' => '203', 'message' => 'This user has no friends.');

        // Format the response object.
        return Array('error' => '200', 'message' => "Found {$count} friends for this user.", 'payload' => $payload);

    }

    /*
    |--------------------------------------------------------------------------
    | Get Suggested Friends
    |--------------------------------------------------------------------------
    |
    | Returns a list of suggested friends, this is calculated by determining
    | friends of a mutual friends
    |
    */

    public static function getSuggestedFriends($userId, $user)
    {
        // Ensure we are getting suggested friends for us
        if (empty($user) || $userId != $user["entityId"])
            return Array("error" => "401", "message" => "You cannot get the friend requests of this user as the user ID you provided does not match your user ID.");

            // Get the settings for the user
            $query = "SELECT preferences.Pref_Lower_Age,
                             preferences.Pref_Upper_Age,
                             preferences.Pref_Sex,
                             entity.Last_CheckIn_Lat AS lat,
                             entity.Last_CheckIn_Long AS lng
                        FROM preferences
                        JOIN entity ON preferences.Entity_Id = entity.Entity_Id
                       WHERE preferences.Entity_Id = :user_id";
            $data = Array(":user_id" => $userId);
            $settings = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);

            switch ($settings["Pref_Sex"]) {
                // MALE
                case 1:
                    $settings["Pref_Sex"] = " Sex = 1";
                    break;
                // FEMALE
                case 2:
                    $settings["Pref_Sex"] = " Sex = 2";
                    break;
                // BOTH
                default:
                    $settings["Pref_Sex"] = " Sex IN(1,2)";
            }
            // Get the names of these people
            $data = Array();
            $data[":user_id"] = $userId;
            $data[":lower"] = $settings["Pref_Lower_Age"];
            $data[":upper"] = $settings["Pref_Upper_Age"];

            // Get the age so we can alter the query
            $age = floor((time() - strtotime($user["dob"])) / 31556926);
            if ($age < 18 && $age >= 16) {
                $data["upper"] = 18;
                $data["lower"] = 16;
            }

            $friendFilter  = "0";
            $requestFilter = "0";

            // Run a query to get friends
            $filterData = Array(":entityId" => $userId);
            $query = "SELECT Entity_Id1 FROM friends WHERE Entity_Id2 = :entityId";
            $friends = Database::getInstance()->fetchAll($query, $filterData);

            if(!empty($friends)){
                $friendFilter = "";
                foreach($friends as $friend)
                {
                    $friendFilter .= $friend["Entity_Id1"] . ", ";
                }
                $friendFilter = rtrim($friendFilter, ", ");
            }

            // Run a query to get friend requests
            $query = "SELECT Req_Receiver AS entityId FROM friend_requests WHERE Req_Sender = :entityId

                      UNION ALL

                      SELECT Req_Sender AS entityId
                      FROM friend_requests
                      WHERE Req_Receiver = :entityId";
            $requests = Database::getInstance()->fetchAll($query, $filterData);

            if(!empty($requests)){
                $requestFilter = "";
                foreach($requests as $friend)
                {
                    $requestFilter .= $friend["entityId"] . ", ";
                }
                $requestFilter = rtrim($requestFilter, ", ");
            }

            $query = "SELECT
                             e.Entity_Id,
                             e.First_Name,
                             e.Last_Name,
                             e.Entity_Id,
                             e.Profile_Pic_Url,
                             e.Last_CheckIn_Dt,
                             e.Sex,
                             TIMESTAMPDIFF(YEAR, e.DOB, CURDATE()) AS Age,
                             (
                                NULL
                             ) AS FC
                      FROM
                             entity e
                      INNER JOIN preferences p
                      ON p.Entity_Id = e.Entity_Id AND p.Pref_Everyone = 1
                      INNER JOIN setting s
                      ON s.Entity_Id = e.Entity_Id AND s.list_visibility = 1
                      WHERE e.Entity_Id <> :user_id
                        AND TIMESTAMPDIFF(YEAR, e.DOB, CURDATE()) BETWEEN :lower AND :upper
                        AND e.Entity_Id NOT IN
                        (
                            ".$requestFilter."
                        )
                        AND e.Entity_Id NOT IN
                        (
                            ".$friendFilter."
                        )
                        AND" . $settings["Pref_Sex"] . "
                        GROUP BY e.Entity_Id
                        LIMIT 200
                        ";

            $res = Database::getInstance()->fetchAll($query, $data);
            if (empty($res))
                return Array("error" => "203", "message" => "None of your friends use the app, therefore we can't suggest mutual friends!");

            // Get the mutual friends of each users
            $res = json_decode(json_encode($res), true);
            $res = array_splice($res, 0, 200);
            for ($i = 0; $i < count($res); $i++) {
                $mutual = self::getMutualFriends($userId, $res[$i]["Entity_Id"]);
                $res[$i]["mutual_friends"] = $mutual;
            }

            return Array("error" => "200", "message" => "We have suggested " . count($res) . " mutual friends for you.", "payload" => $res);
    }

    /*
     |--------------------------------------------------------------------------
     | GET MUTUAL FRIENDS
     |--------------------------------------------------------------------------
     |
     | Returns a list of mutual friends between the logged in user and the
     | specified friend.
     |
     */

    private static function getMutualFriends($userId, $friendId)
    {
        //echo "user id: " . $userId . ", and friend id: " . $friendId . "\n";
        // Check for an invalid match
        if(($userId <= 0 || $friendId <= 0) || $userId == $friendId)
            return 0;

        // Perform the query
        $query = "
                SELECT COUNT(*) AS mutual_count
                FROM entity
                WHERE EXISTS(
                    SELECT *
                    FROM friends
                    WHERE friends.Entity_Id1 = :friendId AND friends.Category <> 4
                      AND friends.Entity_Id2 = entity.Entity_Id
                  )
                  AND EXISTS(
                    SELECT *
                    FROM friends
                    WHERE friends.Entity_Id1 = :userId AND friends.Category <> 4
                      AND friends.Entity_Id2 = entity.Entity_Id
                  )
                 ";

        $data = Array(":friendId" => $friendId, ":userId" => $userId);

        $res = Database::getInstance()->fetch($query, $data);
        if(empty($res))
            return 0;

        return (int)$res->mutual_count;
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

    public static function getFriendRequests($userId, $user = null)
    {
        if(empty($user) || $userId != $user["entityId"])
            return Array("error" => "401", "message" => "You cannot get the friend requests of this user as the user ID you provided does not match your user ID.");

        $DB = Database::getInstance();

        $data = Array(":entity_id" => $userId);
        $query = "SELECT friend_requests.*, entity_id, first_name, last_name, profile_pic_url, email FROM friend_requests JOIN entity ON friend_requests.Req_Sender = entity.Entity_Id WHERE req_receiver = :entity_id";

        $requests = $DB->fetchAll($query, $data);
        if(count($requests) > 0)
        {
            // Get the mutual friends of each users
            $res = json_decode(json_encode($requests), true);
            for ($i = 0; $i < count($res); $i++) {
                $mutual = self::getMutualFriends($userId, $res[$i]["entity_id"]);
                $res[$i]["mutual_friends"] = count($mutual);
            }
            return Array("error" => "200", "message" => "Successfully returned friends for this user", "payload" => $res);
        }
        else
            return Array("error" => "203", "message" => "No new friend requests.");
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

    public static function sendFriendRequest($friendId, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "Your session has expired, please log in again.", "payload" => "");

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
                    "senderName" => $user['firstName'],
                    "receiver" => $friendId,
                    "message" => $user['firstName']. " wants to add you as a friend.",
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
                        "has been sent successfully.");

                else
                    // Only request sent.
                    return Array("error" => "207", "message" => "Partial success: A friend request has been sent, but without a notification");
            }
            else
                // Friend cannot be found.
                return Array("error" => "404", "message" => "The identifier for the friend cannot be found. Please send".
                    " a request to an established user.");

        }
        else
            // Conflict with either friends or friend_requests table.
            return Array("error" => "409", "message" => "You are either already friends with this user, or a friend request has already"
                ." been sent.");

    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ACCEPT FRIEND REQUEST
     |--------------------------------------------------------------------------
     |
     | Accept the friend request. Adds a row to the friends table using the two identifers provided.
     |
     | $friendId - The identifer of the friend.
     |
     | $payload  - the json encoded body to derive the entityId from.
     |
     | @return   - A success or failure message.
     |
     */

    public static function acceptFriendRequest($friendId, $payload, $user)
    {
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

            // Prepare the query for adding a row into the friends table - Insert the reciprecal to, this will improve performance
            $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory)
                      VALUES              (:userId, :friendId, 2, 2) ";
            // Bind the parameters to the query
            $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

            if(Database::getInstance()->insert($query, $data)) {
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory)
                      VALUES              (:friendId, :userId, 2, 2) ";
                // Bind the parameters to the query
                $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);
                $res  = Database::getInstance()->insert($query, $data);
                // Perform the insert, then increment count if this wasn't a duplicate record
                if ($res != 0) {

                    $pushPayload = Array(
                        "senderId" => (int)$user["entityId"],
                        "senderName" => $user["firstName"],
                        "receiver" => (int)$friendId,
                        "message" => $user["firstName"]. " has accepted your friend request.",
                        "type" => 4,
                        "date" => gmdate('Y-m-d H:i:s', time()),
                        "messageId" => NULL,
                        "messageType" => NULL
                    );

                    $server = new \PushServer();
                    return $server->sendNotification($pushPayload);

                } else
                    // Inserting a new row into the friends table has failed.
                    return Array("error" => "400", "message" => "Error adding friend. The query has failed.", "payload" => "");
            }
            else
                // Inserting a new row into the friends table has failed.
                return Array("error" => "400", "message" => "Error adding friend. The query has failed.", "payload" => "");

        } else
            // Deleting the friend-request has failed.
            return Array("error" => "203", "message" => "The friend request does not exist.", "payload" => "");
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
        $category = 1;

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
            $query = "SELECT entity_id FROM entity WHERE Fb_Id = :fb_id";
            $data  = Array(":fb_id" => $friend);

            $entId = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);
            if($entId["entity_id"] != 0) {
                $friend = $entId["entity_id"];

                // Checks to see whether the user combination already exists in the database, uses
                // DUAL for the initial select to ensure we fetch from cache if the friends table is empty.
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory)
                          SELECT :entityA, :entityB, :category, :category
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT fid FROM friends
                              WHERE Entity_Id1 = :entityA
                              AND Entity_Id2 = :entityB
                          )
                          LIMIT 1
                          ";

                // Bind the parameters to the query
                $data = Array(":entityA" => $userId, ":entityB" => $friend, ":category" => $category);

                // Perform the insert, then increment count if this wasn't a duplicate record
                if (Database::getInstance()->insert($query, $data) != 0)
                    $iCount++;

                // Checks to see whether the user combination already exists in the database, uses
                // DUAL for the initial select to ensure we fetch from cache if the friends table is empty.
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory)
                          SELECT :entityA, :entityB, :category, :category
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT fid FROM friends
                              WHERE Entity_Id1 = :entityA
                              AND Entity_Id2 = :entityB
                          )
                          LIMIT 1
                          ";

                // Bind the parameters to the query
                $data = Array(":entityA" => $friend, ":entityB" => $userId, ":category" => $category);

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
        $iCount = $iCount / 2;
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

    public static function removeFriend($friendId, $payload, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "Your session has expired, please re-login");

        // Prepare a query that's purpose will be to delete all records between a user and a current friend.
        $query = "DELETE FROM friends WHERE Entity_Id1 = :userId AND Entity_Id2 = :friendId OR Entity_Id1 = :friendId AND Entity_Id2 = :userId";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "Friend has been removed successfully from all categories.", "params" => "");
        else
            return Array("error" => "203", "message" => "There is no existing relationship from user A to user B, specify a different friend_id");

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
    public static function rejectFriendRequest($friendId, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "Bad request, your are not logged in.");

        // Prepare a query that's purpose will be to delete all records between a user and a current friend.
        $query = "DELETE FROM friend_requests WHERE Req_Sender = :friendId AND Req_Receiver = :userId OR Req_Sender = :userId AND Req_Receiver = :friendId";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
        {
            return Array("error" => "200", "message" => "Friend request has been removed.", "params" => "");
        }
        else
            return Array("error" => "203", "message" => "Friend request does not exist, so it cannot be removed."
            , "params" => "");
    }

    /*
     |--------------------------------------------------------------------------
     | (GET) GET BLOCKED USERS
     |--------------------------------------------------------------------------
     |
     | Returns a list of users who have been blocked by the logged in user
     |
     | @param userId   - The entityId of the logged in user
     | @param user     - Authorises whether the logged in user is the requester
     |
     */

    public static function getBlockedUsers($userId, $user)
    {
        // Validate the requester is the session holder
        if($user["entityId"] != $userId)
            return Array("error" => "401", "You are not authorised to retrieve this users blocked list, retrieve your own.");

        // Get the block list
        $query = "SELECT entity.*, friends.Category
                  FROM entity
                  JOIN friends
                  ON entity.Entity_Id IN (friends.Entity_Id1, friends.Entity_Id2)
                  WHERE Category = 4 AND blockSender = :userId
                  AND Entity_Id <> :userId
                  GROUP BY Entity_Id";
        $data  = Array(":userId" => $userId);

        $res   = Database::getInstance()->fetchAll($query, $data);
        if(count($res) > 0)
            return Array("error" => "200", "message" => "Successfully retrieved " . count($res) . " blocked users.", "payload" => $res);

        return Array("error" => "203", "message" => "You have not blocked any users.");
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

    public static function blockUser($friendId, $userId)
    {
        // Prepare a query that's purpose will be to update the friends table to change the
        // category of a relationship between two users to 4.
        // However, this should only be done in one direction rather than two.
        // For example: user blocks friend, but the query must not do it the other way around too.
        // So...more basic queries for that.
        $query = "UPDATE friends
                  SET Category = 4,
                      blockSender = :userId
                  WHERE (Entity_Id1 = :userId AND Entity_Id2 = :friendId
                    AND Entity_Id2 = :userId AND Entity_Id1 = :friendId)
                    OR (Entity_Id1 = :friendId AND Entity_Id2 = :userId
                    OR  Entity_Id2 = :friendId AND Entity_Id1 = :userId)";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "This user has been blocked successfully.");
        else
        {
            // Prepare the query for adding a row into the friends table - Insert the reciprecal to, this will improve performance
            $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory, blockSender)
                      VALUES              (:userId, :friendId, 4, 4, :userId) ";
            $data  = Array(":userId" => $userId, ":friendId" => $friendId);
            Database::getInstance()->insert($query, $data);
            $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category, oldCategory, blockSender)
                      VALUES              (:friendId, :userId, 4, 4, :userId) ";
            $data  = Array(":userId" => $userId, ":friendId" => $friendId);
            Database::getInstance()->insert($query, $data);
            return Array("error" => "200", "message" => "This user has been blocked successfully.");
        }
        return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UNBLOCK USER
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

    public static function unblockUser($friendId, $userId)
    {
        // Unblocks the user
        $query = "UPDATE friends
                  SET Category = oldCategory,
                      blockSender = 0
                  WHERE (Entity_Id1 = :userId AND Entity_Id2 = :friendId
                     AND Entity_Id2 = :userId AND Entity_Id1 = :friendId)
                    OR (Entity_Id1 = :friendId AND Entity_Id2 = :userId
                    OR  Entity_Id2 = :friendId AND Entity_Id1 = :userId)";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        if (Database::getInstance()->update($query, $data))
        {
            // Delete previously blocked users
            $query = "DELETE FROM friends WHERE oldCategory = 4
                      AND Entity_Id1 = :userId AND Entity_Id2 = :friendId
                       OR Entity_Id1 = :friendId AND Entity_Id2 = :userId";
            Database::getInstance()->delete($query, $data);
            return Array("error" => "200", "message" => "This user has been unblocked successfully.");
        }
        else
            return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist.");
    }
}