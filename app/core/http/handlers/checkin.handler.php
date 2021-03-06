<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Checkin Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Checkin handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler and push server objects
require_once "./app/core/http/handlers/session.handler.php";
require_once "./app/core/http/handlers/user.handler.php";
require_once "./app/core/http/api.push.server.php";

class Checkin
{
    /*
    |--------------------------------------------------------------------------
    | Get Checkin
    |--------------------------------------------------------------------------
    |
    | Returns a list of all checkins dependent on the users preferences.
    | This function
    |
    | @param $args - An array containing all
    |
    */

    public static function getCheckins($args, $user, $limit = 500)
    {
        if(empty($user))
            return Array("error" => "401", "message" => "You are not authorised to access this resource, please re-login to generate a new session token.");

        // Get the preferences for this user, this can then be used to select checkins which are only relevant to this user.
        $query = "SELECT
                          last_checkin_lat                AS last_lat,
                          last_checkin_long               AS last_long,
                          Last_Checkin_Country            AS last_country,
                          TIMESTAMPDIFF(YEAR, DOB, NOW()) AS age,
                          Pref_Chk_Exp                    AS expiry,
                          Pri_Visability                  AS visability,
                          Pref_Facebook                   AS facebook,
                          Pref_Kinekt                     AS kinekt,
                          Pref_Everyone                   AS everyone,
                          Pref_Sex                        AS sex,
                          Pref_Lower_age                  AS lowerAge,
                          Pref_Upper_Age                  AS upperAge,
                          dob

                  FROM entity
                  JOIN preferences
                    ON preferences.Entity_Id = entity.Entity_Id
                  JOIN setting
                    ON setting.Entity_Id = entity.Entity_Id
                  WHERE entity.Entity_id = :entityId
                     OR entity.Fb_Id = :entityId";

        $data = Array(":entityId" => $user["entityId"]);
        $userPreferences = json_decode(json_encode(Database::getInstance()->fetch($query, $data)), true);

        // Check for a bad response
        if(empty($userPreferences))
            return Array("error" => "404", "message" => "No preferences were found for this user so the operation could not complete, please ensure you sent a valid entity id");

        // Determine the radius that the spatial query will be set too
        $userFilter    = "";
        $kinektFriends   = "";
        $facebookFriends = "";
        $users         = "";
        $data          = Array();

        // Determine what age users to show - if we max on the app show oldest users
        // max it to some erroneous value for people with ridiculous Facebook birthdays
        if($userPreferences["upperAge"] == 50)
            $userPreferences["upperAge"] = 130;

        // Get the age so we can alter the query
        $age = floor((time() - strtotime($userPreferences["dob"])) / 31556926);
        if($age < 18 && $age >= 16)
        {
            $userPreferences["upperAge"] = 18;
            $userPreferences["lowerAge"] = 16;
        }

        // Get a string of our friends that we can use to include or exclude them from the query
        $userData = Array();
        $category = "";
        if(empty($userData[":entityId"]))
            $userData[":entityId"] = $user["entityId"];

        $userQuery = "SELECT Entity_Id1 AS id, Category
                      FROM friends
                      WHERE (Entity_Id2 = :entityId OR Entity_Id1 = :entityId)
                        AND (Category = 1 OR Category = 2)
                   GROUP BY Entity_Id1";

        $res = Database::getInstance()->fetchAll($userQuery, $userData);
        if(count($res) > 0)
        {
            foreach($res as $friend)
            {
                $users .= $friend["id"] . ", ";
                if($friend["Category"] == 1)
                    $facebookFriends .= $friend["id"] . ", ";
                else if($friend["Category"] == 2)
                    $kinektFriends .= $friend["id"] . ", ";
            }

            $facebookFriends = rtrim($facebookFriends, ", ");
            $kinektFriends   = rtrim($kinektFriends, ", ");
            $users           = rtrim($users, ", ");
        }

        // Check if we need to get everyone
        if($userPreferences["everyone"] == 1)
        {
            $userFilter = " AND setting.Pri_CheckIn = 2
                            AND TIMESTAMPDIFF(YEAR, ent.DOB, NOW()) BETWEEN :lowerAge AND :upperAge";

            if($users == "")
                $users = 0;

            if($userPreferences["facebook"] == 0 && $userPreferences["kinekt"] == 0)
                $userFilter .= " AND ent.Entity_Id NOT IN ( " . $users . " )";
            else if($userPreferences["facebook"] == 0)
                $userFilter .= " AND ent.Entity_Id NOT IN ( " . $facebookFriends . " )";
            else if($userPreferences["kinekt"] == 0)
                $userFilter .= " AND ent.Entity_Id NOT IN ( " . $kinektFriends . " )";

            // Bind the new params to our data array
            if(empty($data[":lowerAge"]))
                $data[":lowerAge"] = (int)$userPreferences["lowerAge"];
            if(empty($data[":upperAge"]))
                $data[":upperAge"] = (int)$userPreferences["upperAge"];
        }
        // Only return the users checkin and friends
        else
        {
            if($userPreferences["kinekt"] == 0 && $userPreferences["facebook"] == 0 && $userPreferences["everyone"] == 0)
            {
                $userFilter = " AND ent.Entity_Id IN ( " . $user["entityId"] . " )";
            }
            else {
                if ($userPreferences["kinekt"] == 1 && $userPreferences["facebook"] == 1)
                {
                    $users .= ", " . $user["entityId"];
                    $userFilter = " AND ent.Entity_Id IN ( " . $users . " )";
                }
                else if ($userPreferences["kinekt"] == 1)
                {
                    $kinektFriends .= ", " . $user["entityId"];
                    $userFilter = " AND ent.Entity_Id IN ( " . $kinektFriends . " )";
                }
                else if ($userPreferences["facebook"] == 1)
                {
                    $facebookFriends .= ", " . $user["entityId"];
                    $userFilter = " AND ent.Entity_Id IN ( " . $facebookFriends . " )";
                }
            }

        }

        // Check what sexes we need to retrieve
        switch($userPreferences["sex"])
        {
            case 1:
                $userFilter .= " AND ent.Sex = 1";
                break;
            case 2:
                $userFilter .= " AND ent.Sex = 2";
                break;
        }

        // Perform the rest of the binding
        if(empty($data[":entityId"]))
            $data[":entityId"] = (int)$user["entityId"];
        if(empty($data[":currLat"]))
            $data[":currLat"] = (double)$args["curr_lat"];
        if(empty($data[":currLong"]))
            $data[":currLong"] = (double)$args["curr_long"];
        if(empty($data[":expiry"]))
            $data[":expiry"] = (int)$userPreferences["expiry"];

        if($data[":expiry"] == 8)
            $data[":expiry"] = 1000;

        // Get the users based on their preferences
        $query = "SELECT
                        ent.Entity_Id,
                        ent.Profile_Pic_Url,
                        ent.Last_CheckIn_Lat,
                        ent.Last_CheckIn_Long,
                        ent.First_Name AS first_name,
                        ent.Last_CheckIn_Dt AS last_checkin,
                        ent.Last_Name AS last_name,
                        ent.Sex AS Sex,
                        checkins.Place_Name,
                        checkins.Place_Lat,
                        checkins.Place_Long,
                        checkins.Chk_Dt,
                        (
                              SELECT COUNT(DISTINCT Entity_Id)
                                FROM checkins x
                               WHERE x.Place_Lat  = checkins.Place_Lat
                                 AND x.Place_Long = checkins.Place_Long
                         ) AS place_people,
                        checkins.Chk_Id AS checkin_id,
                        checkins.Img_Url AS checkin_photo,
                        (6371 * acos( cos( radians(:currLat) ) * cos( radians(ent.Last_CheckIn_Lat) ) * cos( radians(ent.Last_CheckIn_Long) - radians(:currLong) ) + sin( radians(:currLat) ) * sin( radians(ent.Last_CheckIn_Lat) ) ) ) as distance,
                        (
                            SELECT Category
                            FROM   friends
                            WHERE  (Entity_Id1 = ent.Entity_Id AND Entity_Id2 = :entityId)  OR
                                   (Entity_Id2 = ent.Entity_Id AND Entity_Id1 = :entityId)
                            LIMIT 1
                        ) AS FC,
                        checkins.Chk_Dt AS date,
                        TIMESTAMPDIFF(HOUR, checkins.Chk_Dt, NOW()) AS ago
                  FROM  entity ent
                  JOIN  checkins
                  ON    ent.Entity_Id = checkins.Entity_Id
                  JOIN  setting
                  ON    setting.Entity_Id = ent.Entity_Id
                  WHERE ent.Entity_Id = setting.Entity_Id
                  AND   checkins.Chk_Dt = ent.Last_CheckIn_Dt
                  AND   ent.Create_Dt != ent.Last_CheckIn_Dt
                  AND   ent.Last_CheckIn_Place IS NOT NULL
                  AND   TIMESTAMPDIFF(HOUR, checkins.Chk_Dt, NOW()) <= :expiry
                  AND   TIMESTAMPDIFF(HOUR, checkins.Chk_Dt, NOW()) >= 0
                  ".$userFilter."
                  OR    ent.Entity_Id = :entityId
                  AND   TIMESTAMPDIFF(HOUR, checkins.Chk_Dt, NOW()) <= :expiry
                  AND   TIMESTAMPDIFF(HOUR, checkins.Chk_Dt, NOW()) >= 0
                  AND   checkins.Chk_Dt = ent.Last_CheckIn_Dt
                  AND   ent.Create_Dt != ent.Last_CheckIn_Dt
                  AND   ent.Last_CheckIn_Place IS NOT NULL
                  GROUP BY ent.Entity_Id
                  ORDER BY distance ASC
                  ";

        // Get the results and build the response payload
        $res = Database::getInstance()->fetchAll($query, $data);
        if(empty($res))
            return Array("error" => "203", "message" => "Sorry, it doesn't look like there have been any checkins recently.  Update your privacy settings to see more users!");
        // Ensure all privacy is taken care of here...i.e. no facebook friends must be returned with no last name
        else
        {
            $res = array_splice($res, 0, $limit);
            $users = Array();

            if($res[0]["distance"] > 0)
                $res[0]["distance"] = number_format($res[0]["distance"], 2, '.', '');
            else if($res[0]["distance"] > 1000)
                $res[0]["distance"] = number_format($res[0]["distance"], 0, '.', '');

            foreach($res as $user)
            {
                if(empty($user["FC"]))
                    $user["FC"] = "3";
                if($user["FC"] != 2)
                    $user["last_name"] = "";
                if(empty($user["likes"]) || empty($user["comments"]))
                {
                    $comments = self::getComments($user["checkin_id"], $user["Entity_Id"], $user);
                    $user["likes"] = self::getLikes($user["checkin_id"]);
                    $user["comments"] = $comments["payload"];
                }

                array_push($users, $user);
            }

            return Array("error" => "200", "message" => "Successfully retrieved " . count($res) . " checkins around your location!", "payload" => Array("users" => $users));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Activities
    |--------------------------------------------------------------------------
    |
    | Retrieve all checkins for a user combined with their friends, this will
    | be organised in the list view
    |
    | @param $userId - the id of the user who we are retrieving activities for
    |
    */

    public static function getActivities($userId, $limit = 50)
    {
        if($userId <= 0)
            return Array("error" => "401", "message" => "You are not authorised to access this resource.");

        // Get the friends of this user
        $query = "SELECT Entity_Id1 AS id FROM friends WHERE Entity_Id2 = :userId AND Category <> 4";
        $data  = Array(":userId" => $userId);
        $friends = "";

        $res   = Database::getInstance()->fetchAll($query, $data);
        if(count($res) > 0)
        {
            foreach($res as $friend)
                $friends .= $friend["id"] .= ", ";
            $friends .= $userId;
        }

        // Build the query
        $data  = Array();
        $query = "SELECT
                        E.Entity_Id,
                        E.Fb_Id AS facebook_id,
                        E.Profile_Pic_Url,
                        E.Sex AS Sex,
                        E.Last_CheckIn_Lat,
                        E.Last_CheckIn_Long,
                        E.First_Name AS first_name,
                        E.Last_Name AS last_name,
                        C.Chk_Id,
                        C.place_name,
                        C.place_lat,
                        C.place_long,
                        C.place_people,
                        C.chk_id AS checkin_id,
                        C.img_url AS checkin_photo,
                        C.message,
                        C.Chk_Dt AS date,
                        C.Tagged_Ids,
                        (
                            SELECT COUNT(DISTINCT cc.Entity_Id) FROM checkin_comments cc WHERE cc.Chk_Id = C.Chk_Id
                        ) AS num_comments,
                        (
                            SELECT COUNT(DISTINCT cl.Entity_Id) FROM checkin_likes cl WHERE cl.Chk_Id = C.Chk_Id
                        ) AS num_likes
                  FROM
                        checkins C
                  LEFT JOIN
                        entity E
                    ON
                        E.Entity_Id = C.Entity_Id
                  LEFT JOIN
                        checkin_comments CC
                    ON
                        C.Chk_Id = CC.Chk_Id
                  LEFT JOIN
                        checkin_likes CL
                    ON
                        C.Chk_Id = CL.Chk_Id
                  JOIN
                        setting s
                    ON
                        s.Entity_Id = E.Entity_Id
                  WHERE
                        E.Entity_Id IN (
                            ".$friends."
                        )
                  GROUP BY
                        C.Chk_Id
                  ORDER BY
                        C.Chk_Dt DESC
                  ";



        $res = Database::getInstance()->fetchAll($query, $data);
        if(count($res) > 0)
        {
            // Return tagged user ID's
            $arr = json_decode(json_encode($res), true);
            $arr = array_splice($arr, 0, $limit);

            for($i = 0; $i < count($arr); $i++)
            {
                $arr[$i]["last_name"] = "";
                $tagged    = str_replace(Array("[", "]"), "", $arr[$i]["Tagged_Ids"]);
                $taggedArr = explode(", ", $tagged);

                if(!empty($taggedArr)) {
                    $data = Array();
                    $query = "SELECT first_name, last_name, email, profile_pic_url, entity_id
                           FROM entity
                           WHERE entity_id IN (
                          ";

                    if (count($taggedArr) > 0 && !empty($taggedArr)) {
                        // binds the users to the IN query
                        for ($j = 0; $j < count($taggedArr); $j++) {
                            $query .= ":allow_" . $j;
                            $data[":allow_" . $j] = $taggedArr[$j];
                            if ($j < count($taggedArr) - 1)
                                $query .= ",";
                            else
                                $query .= ")";
                        }
                    } else
                        $query .= "0)";


                    $res = Database::getInstance()->fetchAll($query, $data);
                    if (count($res) > 0) {
                        $arr[$i]["Tagged_Users"] = json_decode(json_encode($res), true);
                    }
                }

                // Set the liked flag
                $arr[$i]["liked"] = self::hasLikedCheckin($userId, $arr[$i]["Chk_Id"]);
            }

            return Array("error" => "200", "message" => "Successfully retrieved " . count($arr) . " checkins.", "payload" => $arr);

        }
        else
            return Array("error" => "203", "message" => "There were not activities to be returned for this user. Make a Check-In!");
    }

    /*
    |--------------------------------------------------------------------------
    | Has Liked Checkin
    |--------------------------------------------------------------------------
    |
    | Determines whether or not a user has liked the checkin.
    |
    */

    private static function hasLikedCheckin($userId, $checkinId)
    {
        $query = "SELECT COUNT(DISTINCT Like_Id) AS liked FROM checkin_likes WHERE Entity_Id = :entity_id AND Chk_Id = :checkin_id";
        $data  = Array(":entity_id" => $userId, ":checkin_id" => $checkinId);

        $res   = Database::getInstance()->fetchAll($query, $data);
        if(!empty($res[0]["liked"]))
            return (string)$res[0]["liked"];

        return "0";
    }

    /*
    |--------------------------------------------------------------------------
    | Get User Checkins
    |--------------------------------------------------------------------------
    |
    | Retrieve all checkins for a user after we authenticate that the user is
    | valid.
    |
    */

    public static function getUserCheckins($args, $user, $limit = 50)
    {
        if(empty($user))
            return Array("error" => "401", "message" => "You are not authorised to access this resource, please re-login to generate a new session token.");

        $query = "SELECT checkins.Chk_Id,
                         checkins.Entity_Id,
                         checkins.Place_Name,
                         checkins.Place_Lat,
                         checkins.Place_Long,
                         checkins.Place_Country,
                         checkins.Place_Category,
                         checkins.Place_Pic_Url,
                         checkins.Img_Url,
                         checkins.Message,
                         checkins.Tagged_Ids,
                         checkins.Chk_Dt,
                         (
                              SELECT COUNT(DISTINCT Entity_Id)
                                FROM checkins x
                               WHERE x.Place_Lat  = checkins.Place_Lat
                                 AND x.Place_Long = checkins.Place_Long
                         ) AS Place_People,
                         entity.First_Name,
                         entity.Fb_Id AS facebook_id,
                         entity.Last_Name,
                         entity.Last_CheckIn_Dt checkin_date,
                         entity.DOB,
                         entity.Email,
                         entity.Sex,
                         entity.Score,
                         (
                            SELECT COUNT(DISTINCT Entity_Id) FROM checkin_comments WHERE checkin_comments.Chk_Id = checkins.Chk_Id
                         ) AS comment_count,
                         (
                            SELECT COUNT(DISTINCT Entity_Id) FROM checkin_likes WHERE checkin_likes.Chk_Id = checkins.Chk_Id
                         ) AS like_count
                  FROM checkins
                  JOIN entity
                    ON checkins.Entity_Id = entity.Entity_Id
                  WHERE checkins.Entity_Id = :entityId
                  ORDER BY checkins.chk_dt DESC";
        $data  = Array(":entityId" => $args["entityId"]);
        $res = Database::getInstance()->fetchAll($query, $data);

        if(count($res) == 0 || empty($res))
            return Array("error" => "203", "message" => "This user does not have any checkins");
        else
        {
            // Return tagged user ID's
            $arr = json_decode(json_encode($res), true);
            $arr = array_splice($arr, 0, $limit);

            for($i = 0; $i < count($arr); $i++)
            {
                $tagged    = str_replace(Array("[", "]"), "", $arr[$i]["Tagged_Ids"]);
                $taggedArr = explode(", ", $tagged);

                $data = Array();
                $query  = "SELECT first_name, last_name, email, profile_pic_url, entity_id
                           FROM entity
                           WHERE entity_id IN (
                          ";

                if(count($taggedArr) > 0 && !empty($taggedArr))
                {
                    // binds the users to the IN query
                    for($j = 0; $j < count($taggedArr); $j++)
                    {
                        $query .= ":allow_" . $j;
                        $data[":allow_".$j] = $taggedArr[$j];
                        if($j < count($taggedArr)-1)
                            $query .= ",";
                        else
                            $query .= ")";
                    }
                }
                else
                    $query .= "0)";


                $res = Database::getInstance()->fetchAll($query, $data);
                if(count($res) > 0)
                {
                    $arr[$i]["Tagged_Users"] = json_decode(json_encode($res), true);
                }

                $arr[$i]["liked"] = self::hasLikedCheckin($user["entityId"], $arr[$i]["Chk_Id"]);
            }

            return Array("error" => "200", "message" => "Successfully retrieved " . count($arr) . " checkins for this user.", "payload" => $arr);
        }

    }

    /*
    |--------------------------------------------------------------------------
    | Create Checkin
    |--------------------------------------------------------------------------
    |
    | Creates a new checkin for the logged in user.  This method is secured
    | by ensuring that the user has a valid session token.  If they do they
    | may proceed to place the checkin, else they are rejected from the system.
    |
    | @param $args : The JSON payload sent to the server detailing the information
    |                on this checkin. An error 400 may be thrown if
    |
    | @return $arr : An array containing either an error detailing what went
    |                wrong with the request, or a payload holding details of
    |                the new checkin.
    |
    */

    public static function createCheckin($args, $token)
    {
        // Extract the data from the payload
        if(!empty($args["image"]["image"]))
            $image = $args["image"]["image"];

        // Args image can be empty, so only throw error if we get here
        if(empty($args["args"]))
            return Array("error" => "400", "message" => "An empty argument array was sent to the server.  Please send a multi-part/form-data form to make the request.");
        else
            $args = $args["args"];

        // Reference a new push server
        $server = new \PushServer();

        // Score modifier variables
        $now = gmdate('Y-m-d H:i:s', time());
        $res = Array();

        $recTagArr       = Array();
        $recFriendArray  = Array();

        // Set default images for places
        $placeImageURL   = SERVER_IP . "/public/img/4bf58dd8d48988d124941735.png";
        $checkinImageURL = "";

        // Check all required fields are set
        if(empty($args["place_name"]) || empty($args["place_lat"]) || empty($args["place_long"]) || empty($args["place_country"]) || (empty($args["place_people"]) && $args["place_people"] != 0))
            return Array("error" => "400", "message" => "The checkin provided must have its [place_name], [place_long], [place_lat], [place_country] and [place_people] set, check the data you sent in the payload.", "payload" => $args);

        // Format unset fields for the checkin object
        if(empty($args["message"]))
            $args["message"] = "";
        if(empty($args["place_category"]))
            $args["place_category"] = "";
        if(empty($args["place_url"]))
            $args["place_url"] = "";
        if(empty($args["tagged_users"]))
            $args["tagged_users"] = "[]";

        // Set the place badge image.
        if(!empty($args["cat_id"]))
        {
            $file_to_open = './public/img/placeBadges/' . $args["cat_id"] . ".png";
            if(file_exists($file_to_open)) {
                $placePic = TRUE;
            }
            else
            {
                $placePic = file_put_contents($file_to_open, file_get_contents($args["place_url"]));
                $placeImageURL = SERVER_IP . "/" . $file_to_open;
            }
        }
        else
        {
            $args["cat_id"] = "";
        }

        // Upload the checkin image if it is set - we catch error here before committing state to db.
        if(!empty($image))
        {
            $res = self::uploadImage($image, $token["entityId"]);
            if($res["error"] == 400)
                return $res;
            else
            {
                $checkinImageURL = SERVER_IP . "/" . $res["imageUrl"];
            }

        }

        // Perform the Checkin insert here...
        $query = "
                    INSERT INTO checkins
                    (
                        Entity_Id,
                        Place_Name,
                        Place_Lat,
                        Place_Long,
                        Place_Country,
                        Place_Category,
                        Place_People,
                        Message,
                        Tagged_Ids,
                        Chk_Dt,
                        Place_Pic_Url,
                        Img_Url
                    )
                    VALUES
                    (
                        :entityId,
                        :placeName,
                        :placeLat,
                        :placeLong,
                        :placeCountry,
                        :placeCategory,
                        :placePeople,
                        :message,
                        :taggedIds,
                        :chkDt,
                        :placePicUrl,
                        :imageUrl
                    )
                 ";


        $placeData = Array(
            ":entityId"      => $token["entityId"],
            ":placeName"     => $args["place_name"],
            ":placeLat"      => $args["place_lat"],
            ":placeLong"     => $args["place_long"],
            ":placeCountry"  => $args["place_country"],
            ":placeCategory" => $args["place_category"],
            ":placePeople"   => $args["place_people"],
            ":message"       => $args["message"],
            ":taggedIds"     => $args["tagged_users"],
            ":chkDt"         => $now,
            ":placePicUrl"   => $placeImageURL,
            ":imageUrl"      => $checkinImageURL
        );

        // Perform the archive query here if the new row inserted
        $res = Database::getInstance()->insert($query, $placeData);
        if($res > 0)
        {
            $checkinId = $res;
            // Everything has succeeded so far, proceed to do scoring and update the user here...
            $query = "UPDATE entity
                      SET score = Score + 10,
                          Last_CheckIn_Lat  = :lat,
                          Last_CheckIn_Long = :lng,
                          Last_CheckIn_Place = :place,
                          Last_CheckIn_Dt = :now,
                          Last_CheckIn_Country = :country
                      WHERE entity_id = :entityId";

            $data  = Array(
                ":entityId" => $token["entityId"],
                ":lat"      => $args["place_lat"],
                ":lng"      => $args["place_long"],
                ":place"    => $args["place_name"],
                ":now"      => $now,
                ":country"  => $args["place_country"]
            );

            Database::getInstance()->update($query, $data);
        }
        else
        {
            return Array("error" => "500", "message" => "Internal server error when attempting to create the new Checkin. Please try again.");
        }

        // Default response for pushes
        $taggedUsers = "[]";
        $taggedPushes = "No push notifications were sent as there were no tagged user recipients";

        // Update score of tagged users and notify them
        if(array_key_exists("tagged_users", $args) && $args["tagged_users"] != "[]")
        {
            $taggedUsers = $args["tagged_users"];
            $taggedCount = count($taggedUsers);
            $success     = 0;
            if($taggedCount > 0) {

                $taggedUsers = rtrim(implode(",", json_decode($args["tagged_users"])), ",");

                foreach (json_decode($args["tagged_users"]) as $taggedUser) {
                    // Perform the tag query
                    $query = "INSERT INTO tags(checkin_id, person_id) VALUES(:checkinId, :personId)";
                    $data = Array(
                        ":checkinId" => $res,
                        ":personId" => (int)$taggedUser
                    );

                    Database::getInstance()->insert($query, $data);

                    // Notify the user, only if they prompted to recieve notifications
                    $query = "SELECT entity_id FROM setting WHERE entity_id = :userId AND notif_tag = 1";
                    $data = Array("userId" => (int)$taggedUser);

                    // This expression will result to std::object or false - this is why we perform a boolean check
                    $recipient = Database::getInstance()->fetch($query, $data);
                    if ($recipient) {

                        // Configure the push payload, we trim the name so that if it was Alexander John, it becomes Alexander.
                        $pushPayload = Array(
                            "senderId" => $token["entityId"],
                            "senderName" => $token["firstName"],
                            "receiver" => (int)$taggedUser,
                            "message" => $token["firstName"] . " has tagged you in a Check-In.",
                            "type" => 4,
                            "date" => $now,
                            "messageId" => $checkinId,
                            "messageType" => 4
                        );

                        $res = $server->sendNotification($pushPayload);
                        if($res["error"] == 203)
                            $success++;
                    }
                }

                if ($success > 0)
                    $res["error"] = 200;

                // Update the master payload
                $taggedPushes = $success . "/" . $taggedCount . " pushes were sent to tagged user recipients";
            }
        }

        // Set up friend push variables
        $filter       = Array("tagged" => $taggedUsers);
        $friends      = Friend::getFriends($token["entityId"], $filter);
        $friendCount  = count($friends);
        $success      = 0;
        $friendPushes = "No friend pushes were sent as you have no friends.";

        // Send a push notification to each of these friends
        if($friendCount > 0 && array_key_exists("payload", $friends)) {
            foreach ($friends["payload"] as $friend) {

                // Notify the user, only if they prompted to recieve notifications
                $query = "SELECT entity_id FROM setting WHERE entity_id = :userId AND notif_tag = 1";
                $data = Array("userId" => $friend["Entity_Id"]);

                // This expression will result to std::object or false - this is why we perform a boolean check
                $recipient = Database::getInstance()->fetch($query, $data);
                if ($recipient) {
                    // Configure the push payload, we trim the name so that if it was Alexander John, it becomes Alexander.
                    $pushPayload = Array(
                        "senderId" => $token["entityId"],
                        "senderName" => $token["firstName"],
                        "receiver" => $recipient->entity_id,
                        "message" => $token["firstName"] . " just checked in to " . $args["place_name"],
                        "type" => 1,
                        "date" => $now,
                        "messageId" => $checkinId,
                        "messageType" => 1
                    );

                    $res = $server->sendNotification($pushPayload);
                    if ($res["error"] == 203)
                        $success++;
                }

                if ($success > 0)
                    $res["error"] = 200;

                // Update the master payload
                $friendPushes = $success . "/" . $friendCount . " pushes were sent to friends who wish to receive notifications.";
            }
        }

        // Process the callback
        if(is_array($res) && array_key_exists("error", $res))
        {
            if(($res["error"] >= 200 && $res["error"] < 300) || $res["error"] == 400)
            {
                $people  = self::getUsersAtLocation($args["place_long"], $args["place_lat"]);
                if($res["error"] >= 400)
                    $res["error"] = 200;
                else
                    $res["error"] = 203;

                $res["message"] = "The Checkin was created successfully.";
                $res["payload"] = Array("details" => $placeData, "tagged_pushes" => $taggedPushes, "friend_pushes" => $friendPushes, "people" => $people["payload"]);
                return $res;
            }
            else
                return Array("error" => "500", "message" => "Internal server error whilst creating this checkin.");
        }
        // Handle the case where no friends or tagged users were present (just the insert ID for a new checkin).
        else if(!is_array($res) && $res > 0)
        {
            $people  = self::getUsersAtLocation($args["place_long"], $args["place_lat"]);
            return Array("errorr" => "200", "message" => "The Checkin was created successfully.", "payload" => Array("details" => $placeData, "people" => $people));
        }
        else
            return Array("error" => "500", "message" => "Internal server error whilst creating this checkin.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get Users at Location
    |--------------------------------------------------------------------------
    |
    | Retrieves all users at a specific location within a given radius.
    |
    | @param $lat    - The latitude of the geo-location point
    | @param $long   - The longitude of the geo-location point
    | @param $radius - Radius of search in km, capped at 200.
    | @param $limit  - The maximum amount of users to be returned as a result
    |                  of the query.
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    private static function getUsersAtLocation($long, $lat)
    {
        // Bind the values and start the query
        $data = Array(":latitude" => $lat, ":longitude" => $long);
        $query = "SELECT DISTINCT entity.Entity_Id,
                                  entity.First_Name AS first_name,
                                  entity.Last_Name AS last_name,
                                  entity.Sex,
                                  entity.Profile_Pic_Url AS profilePic,
                                  checkins.Place_Lat AS latitude,
                                  checkins.Place_Long AS longitude,
                                  checkins.Place_Name,
                                  checkins.Chk_Dt
                  FROM entity
                  JOIN checkins
                    ON checkins.Entity_Id = entity.Entity_Id
                  WHERE Place_Lat = :latitude
                  AND   Place_Long = :longitude
                  GROUP BY entity.Entity_Id
                  ORDER BY first_name ASC
                  ";

        $res = Database::getInstance()->fetchAll($query, $data);

        if(count($res) == 0)
            return Array("error" => "200", "message" => "Congratulations, you are the first person to check into this location");
        else
            return Array("error" => "200", "message" => "Successfully retrieved " . count($res) . " users at this location.", "payload" => $res);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Checkin
    |--------------------------------------------------------------------------
    |
    | Deletes a Checkin from the database
    |
    | @param $args   - Array to specify which resource to delete
    |
    | @return $array - Returns the response object, detailing if the resource
    |                  was deleted successfully or if anything went wrong.
    |
    */

    public static function deleteCheckin($chkId, $userId)
    {
        // Perform the DELETE on the session
        $query = "DELETE FROM checkins WHERE Chk_Id = :checkinId AND Entity_Id = :entityId";
        $data  = Array(":checkinId" => $chkId, ":entityId" => $userId);

        $res = Database::getInstance()->delete($query, $data);
        if($res > 0)
        {
            // Delete from the tags table
            $query = "DELETE FROM tags WHERE checkin_id = :checkinId";
            $data  = Array(":checkinId" => $chkId);

            $res = Database::getInstance()->delete($query, $data);
            if($res > 0)
            {
                return Array("error" => "203", "message" => "The checkin and its associated tagged users were deleted successfully.");
            }
            return Array("error" => "203", "message" => "The checkin was deleted successfully.");
        }
        return Array("error" => "400", "message" => "The checkin could not be deleted.");
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Image
    |--------------------------------------------------------------------------
    |
    | Uploads a new image to the server, this is a private interface as we
    | do not need to
    |
    | @param $image - The image to be upload
    |
    | @return $arr  - If this method fails, it will return an array, if it passes
    |                 then 201 will be returned, indicating we can issue a push
    |                 notification to recipients.
    |
    */

    private static function uploadImage($image, $userId)
    {
        // Upload the checkin image if one was set
        if(!empty($image))
        {
            $allowedExts = Array("jpg", "png");
            $flag = 1;

            $_FILES['file'] = $_FILES["image"];
            $temp = explode(".", $_FILES["file"]["name"]);
            $extension = end($temp);
            $filename = (int)(((rand(21, 500) * rand(39, 9000)) / rand(3,9))) . time() * rand(2, 38) . $userId . "." . $extension;

            $imageURL = './public/img/checkins/c' . $filename;
            if(in_array($extension, $allowedExts))
            {
                if($_FILES['file']['error'] > 0)
                    $flag = 400;
                else
                {
                    //self::fit_image_file_to_width($_FILES["file"]["tmp_name"], 720, $_FILES["file"]["type"]);
                    $flag = move_uploaded_file($_FILES['file']['tmp_name'], $imageURL);
                }

                if ($flag == 1)
                    $flag = 201;
            }
        }

        // Final return type, should be 204, if 0 or array then the upload failed and we return an error back to the client.
        return Array("error" => $flag, "imageUrl" => "public/img/checkins/" . "c" . $filename);
    }

    /*
    |--------------------------------------------------------------------------
    | Compress Image
    |--------------------------------------------------------------------------
    |
    | Compresses the image before we upload, this accounts for all mime types
    | but will default to image/jpeg.  This method will compress to a given
    | size so that we don't store super large images on the server.
    |
    | @param $file - The path to the file to be upload from $_FILES array
    |
    */

    private static function fit_image_file_to_width($file, $w, $mime = 'image/jpeg') {
        list($width, $height) = getimagesize($file);
        $newwidth = $w;
        $newheight = $w * $height / $width;

        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($file);
                break;
            case 'image/png';
                $src = imagecreatefrompng($file);
                break;
            case 'image/bmp';
                $src = imagecreatefromwbmp($file);
                break;
            case 'image/gif';
                $src = imagecreatefromgif($file);
                break;
        }

        $rotate = imagerotate($src, -90, 0);
        $dst = imagecreatetruecolor($newwidth, $newheight);

        imagecopyresampled($dst, $rotate, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($dst, $file);
                break;
            case 'image/png';
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagepng($dst, $file);
                break;
            case 'image/bmp';
                imagewbmp($dst, $file);
                break;
            case 'image/gif';
                imagegif($dst, $file);
                break;
        }

        imagedestroy($dst);
    }

    /*
    |--------------------------------------------------------------------------
    | GET CHECKIN
    |--------------------------------------------------------------------------
    |
    | TODO: ADD DESCRIPTION
    |
    */

    public static function get($args, $user)
    {
        $query = "SELECT
                         entity.First_Name,
                         entity.Last_Name,
                         entity.Profile_Pic_Url,
                         entity.Sex,
                         checkins.Chk_Id,
                         checkins.Entity_Id,
                         checkins.Place_Name,
                         checkins.Place_Lat,
                         checkins.Place_Long,
                         checkins.Place_Country,
                         checkins.Place_Category,
                         checkins.Place_Pic_Url,
                         checkins.Img_Url,
                         checkins.Message,
                         checkins.Tagged_Ids,
                         checkins.Chk_Dt,
                         (
                            SELECT COUNT(1) FROM checkin_comments WHERE Chk_Id = :checkinId
                         ) AS comment_count,
                         (
                            SELECT COUNT(1) FROM checkin_likes WHERE Chk_Id = :checkinId
                         ) AS like_count,
                         (
                              SELECT COUNT(DISTINCT Entity_Id)
                                FROM checkins x
                               WHERE x.Place_Lat  = checkins.Place_Lat
                                 AND x.Place_Long = checkins.Place_Long
                         ) AS Place_People
                  FROM checkins
                  JOIN entity
                  ON checkins.Entity_Id = entity.Entity_Id
                  WHERE Chk_Id = :checkinId";

        $data  = Array(":checkinId" => $args["checkinId"], ":entityId" => $user["entityId"]);

        $res = Database::getInstance()->fetch($query, $data);

        if(count($res) == 0 || empty($res))
            return Array("error" => "203", "message" => "This checkin does not exist");
        else {
            $res = json_decode(json_encode($res), true);
            $comments = self::getComments($args["checkinId"], $user["entityId"], $user);
            $res["comments"] = $comments["payload"];
            $like = self::getLikes($args["checkinId"]);
            $res["likes"] = $like;

            // get the tagged ID's
            // Return tagged user ID's
            $arr = json_decode(json_encode($res), true);

            if (array_key_exists("Tagged_Ids", $arr)) {
                $tagged = str_replace(Array("[", "]"), "", $arr["Tagged_Ids"]);
                $taggedArr = explode(", ", $tagged);

                $data = Array();
                $query = "SELECT first_name, last_name, email, profile_pic_url, entity_id, Sex
                           FROM entity
                           WHERE entity_id IN (
                          ";

                if (count($taggedArr) > 0 && !empty($taggedArr)) {
                    // binds the users to the IN query
                    for ($j = 0; $j < count($taggedArr); $j++) {
                        $query .= ":allow_" . $j;
                        $data[":allow_" . $j] = $taggedArr[$j];
                        if ($j < count($taggedArr) - 1)
                            $query .= ",";
                        else
                            $query .= ")";
                    }
                } else
                    $query .= "0)";


                $res = Database::getInstance()->fetchAll($query, $data);
                if (count($res) > 0) {
                    $arr["Tagged_Users"] = json_decode(json_encode($res), true);
                }

                $arr["liked"] = self::hasLikedCheckin($user["entityId"], $args["checkinId"]);
        }

            return Array("error" => "200", "message" => "Successfully retrieved the Checkin.", "payload" => $arr);
        }

    }

    /*
    |--------------------------------------------------------------------------
    | LIKE CHECKIN
    |--------------------------------------------------------------------------
    |
    | TODO: ADD DESCRIPTION
    |
    */

    public static function likeCheckin($checkinId, $entId)
    {
        $query = "INSERT INTO checkin_likes VALUES('', :entId, :chkId)";
        $data  = Array(":entId" => $entId, ":chkId" => $checkinId);

        $res = Database::getInstance()->insert($query, $data);
        if($res != 0)
        {
            $query = "SELECT Entity_Id FROM checkins WHERE Chk_Id = :checkin_id";
            $data  = Array(":checkin_id" => (int)$checkinId);

            $res   = Database::getInstance()->fetch($query, $data);
            if(!empty($res))
            {
                $checkinOwner = $res->Entity_Id;
                if($checkinOwner != $entId) {
                    $query = "SELECT First_Name, Last_Name, Entity_Id FROM entity WHERE Entity_Id = :user_id";
                    $data = Array(":user_id" => $entId);

                    $res = Database::getInstance()->fetch($query, $data);
                    if (!empty($res)) {
                        $pushPayload = Array(
                            "senderId" => (int)$entId,
                            "senderName" => $res->First_Name,
                            "receiver" => (int)$checkinOwner,
                            "message" => $res->First_Name . " liked your Check-In.",
                            "type" => 4,
                            "date" => gmdate('Y-m-d H:i:s', time()),
                            "messageId" => $checkinId,
                            "messageType" => 4
                        );

                        $server = new \PushServer();
                        $res = $server->sendNotification($pushPayload);
                        if($res["error"] == 203)
                            return Array("error" => "200", "message" => "Successfully liked the checkin, a push notification was sent.");
                    }
                }
            }

            return Array("error" => "200", "message" => "Successfully liked the checkin, no push notification was sent.");
        }

        return Array("error" => "203", "message" => "Invalid resource, please try again.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET COMMENTS
     |--------------------------------------------------------------------------
     |
     | Get all of the comments for a checkin.
     |
     | @param $checkInId - The identifier for the checkIn.
     |
     | @param $userId    - The identifier of the user that called the endpoint.
     |
     | @return             A list of comments about a checkIn with/or a response message.
     */

    public static function getComments($checkInId, $userId, $user)
    {

        // Get all of the comments
        $query = "SELECT DISTINCT ent.Entity_Id, ent.Profile_Pic_Url, ent.First_Name, ent.Last_Name, ent.Sex AS Sex, ent.Last_CheckIn_Place, comments.Content, comments.Comment_Dt
                  FROM   checkin_comments comments
                  JOIN   entity ent
                  ON     comments.Entity_Id = ent.Entity_Id
                  WHERE  comments.Chk_Id = :checkInId
                  ";

        // Bind the parameters to the query
        $data = Array(":checkInId" => $checkInId);

        $checkInComments = Database::getInstance()->fetchAll($query, $data);

        if(!empty($checkInComments))
        {
            // Check to see if any comments are authored by a blocked user. If so remove those comments from
            // the array that will be returned as the payload.
            $query = "
                  SELECT Entity_Id2
                  FROM  friends
                  WHERE Entity_Id1 = :userId
                  AND   Category   = 4
                  ";

            // Bind the parameters to the query
            $data = Array(":userId" => $userId);

            // Get the blocked users
            $blockedUsers = Database::getInstance()->fetchAll($query, $data);

            if(!empty($blockedUsers))
            {
                // Cycle through. If we have any comments that are authored by a blocked user, then remove them
                // from the array.
                $count = 0;
                foreach ($checkInComments as $comment) {
                    foreach ($blockedUsers as $blockedUser) {

                        // If we find a blocked user, remove them from the results array.
                        if ($comment['Entity_Id'] == $blockedUser['Entity_Id2'])
                            unset($checkInComments[$count]);
                    }

                    $count++;
                }

                // Since splice didn't work, I had to use unset, which meant that the array's index's got all messed up.
                // Effectively, splice does a similar thing to this by reassigning an array, so it's not too bad of a cost
                // to use array_values to fix the indexing.
                $finalComments = array_values($checkInComments);

                return Array("error" => "200", "message" => "Results have been collected successfully.", "payload" => $finalComments);
            }
            else
                // No blocked users, so return array in normal format.
                return Array("error" => "200", "message" => "Results have been collected successfully.", "payload" => $checkInComments);
        }
        else
            // No results found.
            return Array("error" => "200", "message" => "There were no comments for this checkin.", "payload" => "No comments for this checkin");
    }

    /*
     |--------------------------------------------------------------------------
     | GET LIKES
     |--------------------------------------------------------------------------
     |
     | Get all of the comments for a checkin.
     |
     | @param $checkInId - The identifier for the checkIn.
     |
     | @param $userId    - The identifier of the user that called the endpoint.
     |
     | @return             A list of comments about a checkIn with/or a response message.
     */

    private static function getLikes($chkId)
    {
        $query = "SELECT entity.Entity_Id, entity.First_Name, entity.Last_Name, entity.Sex AS Sex, entity.Profile_Pic_Url, entity.Last_CheckIn_Place
                  FROM checkin_likes
                  LEFT JOIN entity
                  ON entity.Entity_Id = checkin_likes.Entity_Id
                  WHERE Chk_Id = :checkinId
                  GROUP BY Entity_Id";

        $data  = Array(":checkinId" => $chkId);

        $res = Database::getInstance()->fetchAll($query, $data);
        if(count($res) > 0)
        {
            $arr = json_decode(json_encode($res), true);
            return $arr;
        }
        return "No likes for this checkin";
    }


    /*
     |--------------------------------------------------------------------------
     | (POST) ADD COMMENT
     |--------------------------------------------------------------------------
     |
     | Add a new comment to a checkin.
     |
     | @param $checkInId - The identifier of the checkin.
     |
     | @param $payload   - The json encoded user information: we use entityId and message.
     |
     | @return           - A success or failure message depending on the outcome.
     |
     */

    public static function addComment($checkInId, $payload, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // Prepare a query that's purpose will be to add a new comment to a check in
        $query = "INSERT INTO checkin_comments(Entity_Id, Chk_Id, Content, Comment_Dt)
                  VALUES (:userId, :checkInId, :message, :now)
                 ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":checkInId" => $checkInId, ":message" => $payload['message'], ":now" => gmdate('Y-m-d H:i:s', time()));

        // Perform the insert, then increment count if this wasn't a duplicate record
        if (Database::getInstance()->insert($query, $data)) {
            $query = "SELECT Entity_Id FROM checkins WHERE Chk_Id = :checkin_id";
            $data  = Array(":checkin_id" => (int)$checkInId);

            $res   = Database::getInstance()->fetch($query, $data);
            if(!empty($res)) {
                $checkinOwner = $res->Entity_Id;
                if($checkinOwner != $user["entityId"]) {
                    $query = "SELECT First_Name, Last_Name, Entity_Id FROM entity WHERE Entity_Id = :user_id";
                    $data = Array(":user_id" => $user["entityId"]);

                    $res = Database::getInstance()->fetch($query, $data);
                    if (!empty($res)) {
                        $pushPayload = Array(
                            "senderId" => (int)$user["entityId"],
                            "senderName" => $res->First_Name,
                            "receiver" => (int)$checkinOwner,
                            "message" => $res->First_Name . " commented on your Check-In!",
                            "type" => 4,
                            "date" => gmdate('Y-m-d H:i:s', time()),
                            "messageId" => $checkInId,
                            "messageType" => 4
                        );

                        $server = new \PushServer();
                        $server->sendNotification($pushPayload);
                    }
                }

                // Send a message to each user who was at the checkin
                $query = "SELECT e.First_Name, e.Last_Name, e.Entity_Id AS Entity_Id, s.Notif_CheckIn_Activity
                              FROM entity e
                              JOIN checkin_comments cc
                              ON e.Entity_Id = cc.Entity_Id
                              JOIN setting s
                              ON e.Entity_Id = s.Entity_Id
                              WHERE e.Entity_Id <> :user_id
                              AND e.Entity_Id <> :checkin_owner
                              AND s.Notif_CheckIn_Activity = 1
                              AND Chk_Id = :checkin_id";

                $data  = Array(":user_id" => (int)$user["entityId"], ":checkin_id" => $checkInId, ":checkin_owner" => (int)$checkinOwner);
                $res = Database::getInstance()->fetchAll($query, $data);
                if(!empty($res))
                {
                    foreach($res as $recipient)
                    {
                        $pushPayload = Array(
                            "senderId" => (int)$user["entityId"],
                            "senderName" => $user["firstName"],
                            "receiver" => (int)$recipient["Entity_Id"],
                            "message" => $user["firstName"] . " commented on a Check-In you were active on!",
                            "type" => 4,
                            "date" => gmdate('Y-m-d H:i:s', time()),
                            "messageId" => $checkInId,
                            "messageType" => 4
                        );

                        $server = new \PushServer();
                        $server->sendNotification($pushPayload);
                    }
                }
            }
            return Array("error" => "200", "message" => "Comment has been added successfully.");
        }
        else
            return Array("error" => "400", "message" => "Adding the new comment has failed.");
    }

}