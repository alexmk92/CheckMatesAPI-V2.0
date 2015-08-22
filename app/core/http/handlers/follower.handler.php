<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Follower Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Follower handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler object
require_once "./app/core/http/handlers/session.handler.php";
require_once "./app/core/http/api.push.server.php";

class Follower
{

    /*
    |--------------------------------------------------------------------------
    | Get Followers
    |--------------------------------------------------------------------------
    |
    | Returns an array of all Followers that the user has.
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

    public static function getFollowers($userId, $filter = NULL)
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
                  JOIN followers
                  ON entity.Entity_Id = followers.following
                  WHERE follower = :entity_id AND Category != 2
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
            return Array('error' => '203', 'message' => 'This user has no Followers.');

        // Format the response object.
        return Array('error' => '200', 'message' => "Found {$count} Followers for this user.", 'payload' => $payload);

    }

    /*
    |--------------------------------------------------------------------------
    | Get Suggested Followers
    |--------------------------------------------------------------------------
    |
    | Returns a list of suggested Followers, this is calculated by determining
    | Followers of a mutual Followers
    |
    */

    public static function getSuggestedFollowers($userId, $user)
    {
        // Ensure we are getting suggested Followers for us
        if (empty($user) || $userId != $user["entityId"])
            return Array("error" => "401", "message" => "You cannot get the Follower requests of this user as the user ID you provided does not match your user ID.");

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

            $FollowerFilter  = "0";

            // Run a query to get Followers
            $filterData = Array(":entityId" => $userId);
            $query = "SELECT following FROM followers WHERE follower = :entityId";
            $Followers = Database::getInstance()->fetchAll($query, $filterData);

            if(!empty($Followers)){
                $FollowerFilter = "";
                foreach($Followers as $Follower)
                {
                    $FollowerFilter .= $Follower["following"] . ", ";
                }
                $FollowerFilter = rtrim($FollowerFilter, ", ");
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
                            ".$FollowerFilter."
                        )
                        AND" . $settings["Pref_Sex"] . "
                        GROUP BY e.Entity_Id
                        LIMIT 200
                        ";

            $res = Database::getInstance()->fetchAll($query, $data);
            if (empty($res))
                return Array("error" => "203", "message" => "None of your Followers use the app, therefore we can't suggest mutual Followers!");

            // Get the mutual Followers of each users
            $res = json_decode(json_encode($res), true);
            $res = array_splice($res, 0, 100);
            for ($i = 0; $i < count($res); $i++) {
                $mutual = self::getMutualFollowers($userId, $res[$i]["Entity_Id"]);
                $res[$i]["mutual_Followers"] = $mutual;
            }

            return Array("error" => "200", "message" => "We have suggested " . count($res) . " mutual Followers for you.", "payload" => $res);
    }

    /*
     |--------------------------------------------------------------------------
     | GET MUTUAL FollowerS
     |--------------------------------------------------------------------------
     |
     | Returns a list of mutual Followers between the logged in user and the
     | specified Follower.
     |
     */

    private static function getMutualFollowers($userId, $FollowerId)
    {
        //echo "user id: " . $userId . ", and Follower id: " . $FollowerId . "\n";
        // Check for an invalid match
        if(($userId <= 0 || $FollowerId <= 0) || $userId == $FollowerId)
            return 0;

        // Perform the query
        $query = "
                SELECT COUNT(*) AS mutual_count
                FROM entity
                WHERE EXISTS(
                    SELECT *
                    FROM followers f
                    WHERE f.follower = :FollowerId AND f.Category <> 2
                      AND f.following = entity.Entity_Id
                  )
                  AND EXISTS(
                    SELECT *
                    FROM followers f
                    WHERE f.follower = :userId AND f.Category <> 2
                      AND f.following = entity.Entity_Id
                  )
                 ";

        $data = Array(":FollowerId" => $FollowerId, ":userId" => $userId);

        $res = Database::getInstance()->fetch($query, $data);
        if(empty($res))
            return 0;

        return (int)$res->mutual_count;
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) Follower user
     |--------------------------------------------------------------------------
     |
     | Accept the Follower request. Adds a row to the Followers table using the two identifers provided.
     |
     | $FollowerId - The identifer of the Follower.
     |
     | $payload  - the json encoded body to derive the entityId from.
     |
     | @return   - A success or failure message.
     |
     */

    public static function followUser($FollowerId, $payload, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");
        
        $query = "INSERT INTO followers(follower, following, Category, oldCategory)
                  VALUES (:userId, :FollowerId, 1, 1) ";
        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":FollowerId" => $FollowerId);
        $res  = Database::getInstance()->insert($query, $data);
        // Perform the insert, then increment count if this wasn't a duplicate record
        if ($res != 0) {

            $pushPayload = Array(
                "senderId" => (int)$user["entityId"],
                "senderName" => $user["firstName"],
                "receiver" => (int)$FollowerId,
                "message" => $user["firstName"]. " has followed you.",
                "type" => 4,
                "date" => gmdate('Y-m-d H:i:s', time()),
                "messageId" => NULL,
                "messageType" => NULL
            );

            $server = new \PushServer();
            $res = $server->sendNotification($pushPayload);
            if($res["error"] == 203)
                return Array('error' => 203, 'message' => 'Successfully followed the user and notified them via a push notification.');
            else
                return Array('error' => 200, 'message' => 'Successfully followed the user, however they do not wish to receive notifications');

        } else
            // Inserting a new row into the Followers table has failed.

        return Array("error" => "400", "message" => "Error adding Follower. The query has failed.", "payload" => "");
    }

    /*
    |--------------------------------------------------------------------------
    | (POST) Add Followers
    |--------------------------------------------------------------------------
    |
    | Adds the Followers in the given array to the database for this user, the
    | id passed to this method will be the facebook id of the list of users
    | as well as the facebook_id of the current user.
    |
    | @param $Followers  - An array of Facebook users
    | @param $category - The category for this relation - 1 = FB, 2 = Kinekt 3 = ALL 4 = Blocked
    | @param $userId   - The ID for this user (must be facebook ID)
    |
    */

    public static function addFollowers($Followers, $category = 1, $userId)
    {
        // Create an array of unique Followers
        $Followers = array_filter(array_unique(explode(",", $Followers)));

        // Set variables for response - iCount = insertCount, aCount = noAccountCount and fCount = FollowersCount
        $iCount = 0;
        $aCount = 0;
        $fCount = sizeof($Followers);

        // Ensure that we have sent some users to add, if not then return an error 400
        if($iCount == $fCount)
            return Array('error' => '400', 'message' => 'Bad request, no users were sent in the POST body.');

        // Loop through the Followers array and insert each unique user
        foreach ($Followers as $Follower)
        {
            $query = "SELECT entity_id FROM entity WHERE Fb_Id = :fb_id";
            $data  = Array(":fb_id" => $Follower);

            $entId = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);
            if($entId["entity_id"] != 0) {
                $Follower = $entId["entity_id"];

                // Checks to see whether the user combination already exists in the database, uses
                // DUAL for the initial select to ensure we fetch from cache if the Followers table is empty.
                $query = "INSERT INTO followers(follower, following, Category, oldCategory)
                          SELECT :entityA, :entityB, :category, :category
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT fid FROM followers
                              WHERE follower = :entityA
                              AND following = :entityB
                          )
                          LIMIT 1
                          ";

                // Bind the parameters to the query
                $data = Array(":entityA" => $userId, ":entityB" => $Follower, ":category" => $category);

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
        return Array('error' => '200', 'message' => "{$msg} out of {$fCount} Followers, {$iCount} were inserted, {$diff} were duplicates and {$aCount} of your friends does not have a Kinekt account.");
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE Follower
    |--------------------------------------------------------------------------
    |
    | Removes a Follower. It is presumed that the Follower will be deleted from all categories.
    | Note: when testing, if you send a Follower request then delete, it will not be removed because it
    | will be in the Follower-requests table.
    |
    | @param $FollowerId - The identifer of the Follower to remove.
    |
    | @param $payload  - ALl of the Curl JSON information.
    |
    | @return          A success or failure return array depending on the outcome.
    |
    */

    public static function unfollow($FollowerId, $payload, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "Your session has expired, please re-login");

        // Prepare a query that's purpose will be to delete all records between a user and a current Follower.
        $query = "DELETE FROM followers WHERE follower = :userId AND following = :FollowerId";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":FollowerId" => $FollowerId);

        // Delete all records of Followership between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "Follower has been removed successfully from all categories.", "params" => "");
        else
            return Array("error" => "203", "message" => "There is no existing relationship from user A to user B, specify a different Follower_id");

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
        $query = "SELECT entity.*, followers.Category
                  FROM entity
                  JOIN followers
                  ON entity.Entity_Id IN (followers.follower, followers.following)
                  WHERE Category = 2 AND blockSender = :userId
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
     | Change the category of a Followership between two people in order to block
     | the lines of communication. Simply updates all rows that pertain to the two
     | user identifiers.
     |
     | @param userId   - The entityId. Sent as a header instead of as a part of a payload
     |                   that is authenticated because the PUT verb was used.
     |
     | @param FollowerId - The identifier of the 'Follower'.
     |
     |
     */

    public static function blockUser($FollowerId, $userId)
    {
        // Prepare a query that's purpose will be to update the Followers table to change the
        // category of a relationship between two users to 4.
        // However, this should only be done in one direction rather than two.
        // For example: user blocks Follower, but the query must not do it the other way around too.
        // So...more basic queries for that.
        $query = "UPDATE followers
                  SET Category = 2,
                      blockSender = :userId
                  WHERE follower = :userId AND following = :FollowerId";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":FollowerId" => $FollowerId);

        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "This user has been blocked successfully.");
        else
        {
            // Prepare the query for adding a row into the Followers table - Insert the reciprecal to, this will improve performance
            $query = "INSERT INTO followers(follower, following, Category, oldCategory, blockSender)
                      VALUES (:userId, :FollowerId, 2, 2, :userId) ";
            $data  = Array(":userId" => $userId, ":FollowerId" => $FollowerId);
            $res = Database::getInstance()->insert($query, $data);
            return Array("error" => "200", "message" => "This user has been blocked successfully.");
        }
        return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UNBLOCK USER
     |--------------------------------------------------------------------------
     |
     | Change the category of a Followership between two people in order to block
     | the lines of communication. Simply updates all rows that pertain to the two
     | user identifiers.
     |
     | @param userId   - The entityId. Sent as a header instead of as a part of a payload
     |                   that is authenticated because the PUT verb was used.
     |
     | @param FollowerId - The identifier of the 'Follower'.
     |
     |
     */

    public static function unblockUser($FollowerId, $userId)
    {
        // Unblocks the user
        $query = "UPDATE followers
                  SET Category = oldCategory,
                      blockSender = 0
                  WHERE follower = :userId AND following = :FollowerId";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":FollowerId" => $FollowerId);

        if (Database::getInstance()->update($query, $data))
        {
            // Delete previously blocked users
            $query = "DELETE FROM followers WHERE oldCategory = 2
                      AND follower = :userId AND following = :FollowerId
                       OR follower = :FollowerId AND following = :userId";
            Database::getInstance()->delete($query, $data);
            return Array("error" => "200", "message" => "This user has been unblocked successfully.");
        }
        else
            return Array("error" => "409", "message" => "Conflict: The relationship between the two users does not exist.");
    }
}