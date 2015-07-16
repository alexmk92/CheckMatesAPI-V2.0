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
        $query = "SELECT DISTINCT Entity_Id, Fb_Id, First_Name, Last_Name, Profile_Pic_Url, Last_CheckIn_Place, Category
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
        if(empty($user) || $userId != $user["entityId"])
            return Array("error" => "401", "message" => "You cannot get the friend requests of this user as the user ID you provided does not match your user ID.");

        // Get all users with more than 1 mutual friend
        $query = "
                    SELECT y.Entity_Id2
                      FROM friends x
                      LEFT
                      JOIN friends y
                        ON y.Entity_Id1 = x.Entity_Id2
                       AND y.Entity_Id2 <> x.Entity_Id1
                      LEFT
                      JOIN friends z
                        ON z.Entity_Id2 = y.Entity_Id2
                       AND z.Entity_Id1 = x.Entity_Id1
                     WHERE x.Entity_Id1 = :user_id
                       AND z.Entity_Id1 IS NULL
                       AND y.Entity_Id2 IS NOT NULL
                       AND y.Category != 4
                       AND x.Category != 4
                       AND y.Entity_Id2 NOT IN (
                           SELECT Req_Receiver FROM friend_requests WHERE Req_Receiver = y.Entity_Id2
                           AND Req_Sender = :user_id
                       )
                       AND x.Entity_Id1 NOT IN (
                           SELECT Req_Receiver FROM friend_requests WHERE Req_Receiver = x.Entity_Id1
                           AND Req_Sender = :user_id
                       )
                       AND y.Entity_Id2 NOT IN (SELECT Entity_Id1 FROM friends WHERE Entity_Id2 = :user_id UNION SELECT Entity_Id2 FROM friends WHERE Entity_Id1 = :user_id)
                     GROUP
                        BY y.Entity_Id2
                    HAVING COUNT(*) >= 1;
                ";
        $data = Array(":user_id" => $userId);
        $res = Database::getInstance()->fetchAll($query, $data);

        // Build the mutual checkin query
        $query = "SELECT cy.entityId AS Entity_Id2
                  FROM checkinArchive cx
                  LEFT
                  JOIN checkinArchive cy
                    ON (cx.placeLat = cy.placeLat AND cx.placeLng = cy.placeLng) AND cy.entityId <> :user_id
                    WHERE cx.entityId = :user_id
                    ";
        if(count($res) > 0) {
            $query .= " AND cy.entityId NOT IN (";
            for ($i = 0; $i < count($res); $i++) {
                $query .= ":user_" . $i;
                //$query .= $res[$i]["Entity_Id2"];
                $data[":user_" . $i] = $res[$i]["Entity_Id2"];

                if ($i == count($res) - 1)
                    $query .= ")";
                else
                    $query .= ", ";
            }
            $query .= " GROUP BY cy.entityId";
        }
        else
            $query .= " GROUP BY cy.entityId";

        $res2 = Database::getInstance()->fetchAll($query, $data);
        if(count($res2) > 0)
            $res = array_merge($res, $res2);

        if(count($res) > 0) {
            // Get the settings for the user
            $query = "SELECT preferences.Pref_Lower_Age,
                             preferences.Pref_Upper_Age,
                             preferences.Pref_Sex
                        FROM preferences
                       WHERE Entity_Id = :user_id";

            $data  = Array(":user_id" => $userId);
            $settings = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);

            switch($settings["Pref_Sex"])
            {
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
            if($age < 18 && $age >= 16)
            {
                $data["upper"] = 18;
                $data["lower"] = 16;
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
                             f.Category AS FC,
                             s.list_visibility,
                             p.Pref_Everyone
                      FROM
                             entity e
                      LEFT JOIN
                             setting s
                      ON
                             e.Entity_Id IN(s.Entity_Id)
                       AND   (s.list_visibility = 1 OR s.list_visibility = null)
                      LEFT JOIN
                             preferences p
                      ON
                             e.Entity_Id IN(p.Entity_Id)
                       AND   (p.Pref_Everyone = 1 OR p.Pref_Everyone = null)
                      LEFT
                      JOIN
                             friends f
                      ON
                             e.Entity_Id
                      IN    (f.Entity_Id1, f.Entity_Id2)
                      AND    f.Category NOT IN (1, 2, 3, 4)
                      WHERE e.Entity_Id IN (";

            for($i = 0; $i < count($res); $i++)
            {
                $query .= ":user_" . $i;
                $data[":user_".$i] = $res[$i]["Entity_Id2"];

                if($i == count($res)-1)
                    $query .= ")";
                else
                    $query .= ", ";
            }

            $query .= " AND e.Entity_Id != :user_id
                        AND TIMESTAMPDIFF(YEAR, e.DOB, CURDATE()) BETWEEN :lower AND :upper
                        AND e.Entity_Id NOT IN
                        (
                            SELECT Req_Receiver FROM friend_requests WHERE Req_Sender = :user_id

                            UNION ALL

                            SELECT Req_Receiver FROM friend_requests WHERE Req_Sender = e.Entity_Id AND Req_Receiver = :user_id
                        )
                        AND e.Entity_Id NOT IN
                        (
                            SELECT Entity_Id1 FROM friends WHERE Entity_Id2 = :user_id

                            UNION ALL

                            SELECT Entity_Id2 FROM friends WHERE Entity_Id1 = :user_id
                        )
                        AND" . $settings["Pref_Sex"] . "
                        GROUP BY e.Entity_Id";

            $res = Database::getInstance()->fetchAll($query, $data);
            if(empty($res))
                return Array("error" => "404", "message" => "None of your friends use the app, therefore we can't suggest mutual friends!");

            // Get the mutual friends of each users
            $res = json_decode(json_encode($res), true);
            $res = array_splice($res, 0, 100);
            for ($i = 0; $i < count($res); $i++) {
                $mutual = self::getMutualFriends($userId, $res[$i]["Entity_Id"]);
                $res[$i]["mutual_friends"] = $mutual;
            }
            return Array("error" => "200", "message" => "We have suggested " . count($res) . " mutual friends for you.", "payload" => $res);
        }
        else
            return Array("error" => "404", "message" => "None of your friends use the app, therefore we can't suggest mutual friends!");
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
                    SELECT First_Name AS first_name,
                           Last_Name AS last_name,
                           Profile_Pic_Url AS profile_pic,
                           Entity_Id AS entity_id,
                           DOB AS dob,
                           Fb_Id AS facebook_id,
                           Score AS score,
                           Email AS email
                    FROM
                    (
                        SELECT entity.First_Name,
                               entity.Last_Name,
                               entity.Profile_Pic_Url,
                               entity.Entity_Id,
                               entity.DOB,
                               entity.Fb_Id,
                               entity.Score,
                               entity.Email
                        FROM entity
                        JOIN friends
                          ON (entity.Entity_Id = friends.Entity_Id1 OR entity.Entity_Id = friends.Entity_Id2)
                        WHERE (friends.Entity_Id1 = :userId OR friends.Entity_Id2 = :userId)
                        AND Entity_Id <> :userId
                        AND Entity_Id <> :friendId

                        UNION ALL

                        SELECT entity.First_Name,
                               entity.Last_Name,
                               entity.Profile_Pic_Url,
                               entity.Entity_Id,
                               entity.DOB,
                               entity.Fb_Id,
                               entity.Score,
                               entity.Email
                        FROM entity
                        JOIN friends
                          ON (entity.Entity_Id = friends.Entity_Id1 OR entity.Entity_Id = friends.Entity_Id2)
                        WHERE (friends.Entity_Id1 = :friendId OR friends.Entity_Id2 = :friendId)
                        AND Entity_Id <> :userId
                        AND Entity_Id <> :friendId

                    ) friend_list
                    GROUP BY entity_id
                    HAVING COUNT(*) > 2
                    ORDER BY first_name ASC
                 ";

        $data = Array(":friendId" => $friendId, ":userId" => $userId);

        $res = Database::getInstance()->fetchAll($query, $data);
        return count($res);
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
            $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category)
                      VALUES              (:userId, :friendId, 2) ";
            // Bind the parameters to the query
            $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

            if(Database::getInstance()->insert($query, $data)) {
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category)
                      VALUES              (:friendId, :userId, 2) ";
                // Bind the parameters to the query
                $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);
                $res  = Database::getInstance()->insert($query, $data);
                // Perform the insert, then increment count if this wasn't a duplicate record
                if ($res != 0) {

                    $pushPayload = Array(
                        "senderId" => (int)$user["entityId"],
                        "senderName" => $user["firstName"]. " " . $user["lastName"],
                        "receiver" => (int)$friendId,
                        "message" => $user["firstName"]. " " . $user["lastName"] . " has accepted your friend request.",
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
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category)
                          SELECT :entityA, :entityB, :category
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
                $query = "INSERT INTO friends(Entity_Id1, Entity_Id2, Category)
                          SELECT :entityA, :entityB, :category
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
            $pushPayload = Array(
                "senderId" => (int)$user["entityId"],
                "senderName" => $user["firstName"]. " " . $user["lastName"],
                "receiver" => (int)$friendId,
                "message" => $user["firstName"]. " " . $user["lastName"] . " rejected your friend request.",
                "type" => 4,
                "date" => gmdate('Y-m-d H:i:s', time()),
                "messageId" => NULL,
                "messageType" => NULL
            );

            $server = new \PushServer();
            $server->sendNotification($pushPayload);
            return Array("error" => "200", "message" => "Friend request has been removed.", "params" => "");
        }
        else
            return Array("error" => "203", "message" => "Friend request does not exist, so it cannot be removed."
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

    public static function blockUser($friendId, $userId)
    {
        // Prepare a query that's purpose will be to update the friends table to change the
        // category of a relationship between two users to 4.
        // However, this should only be done in one direction rather than two.
        // For example: user blocks friend, but the query must not do it the other way around too.
        // So...more basic queries for that.
        $query = "UPDATE friends
                  SET Category = 4
                  WHERE (Entity_Id1 = :userId AND Entity_Id2 = :friendId
                    AND Entity_Id2 = :userId AND Entity_Id1 = :friendId)
                    OR (Entity_Id1 = :userId AND Entity_Id2 = :friendId
                    OR  Entity_Id2 = :userId AND Entity_Id1 = :friendId)";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "This user has been blocked successfully.");
        else
            return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist.");
    }

}