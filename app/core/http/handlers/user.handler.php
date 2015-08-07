<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| User Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a User handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler object and any other handler bridges
require_once "./app/core/http/handlers/session.handler.php";
require_once "friend.handler.php";

class User
{

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

    public static function getUsersAtLocation($long, $lat, $limit = 250, $user)
    {
        // Get the users privacy settings
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
        $spatialFilter = "";
        $users         = "";
        $userFilter    = "0";
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
        if(empty($userData[":category"]))
        {
            if(($userPreferences["facebook"] == 1 && $userPreferences["kinekt"] == 1) ||
                ($userPreferences["facebook"] == 0 && $userPreferences["kinekt"] == 0)
            )
                $userData[":category"] = "1 OR Category = 2";
            else
            {
                if($userPreferences["facebook"] == 1)
                    $userData[":category"] = "1";
                if($userPreferences["kinekt"] == 1)
                    $userData[":category"] = "2";
            }
        }
        if(empty($userData[":entityId"]))
            $userData[":entityId"] = $user["entityId"];

        $userQuery = "SELECT DISTINCT Entity_Id1 AS id FROM friends WHERE Entity_Id2 = :entityId AND Category = :category GROUP BY Entity_Id1";
        $res = Database::getInstance()->fetchAll($userQuery, $userData);
        if(count($res) > 0)
        {
            foreach($res as $friend)
            {
                $users .= $friend["id"] .= ", ";
            }

            $users = rtrim($users, ", ");
        }
        // Check if we need to get everyone
        if($userPreferences["everyone"] == 1)
        {
            $userFilter = " AND setting.list_visibility = 1
                            AND TIMESTAMPDIFF(YEAR, entity.DOB, NOW()) BETWEEN :lowerAge AND :upperAge";

            if($users == "")
                $users = 0;

            if($userPreferences["facebook"] == 0 || $userPreferences["kinekt"] == 0)
                $userFilter .= " AND entity.Entity_Id NOT IN ( " . $users . " )";

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
                $userFilter = " AND entity.Entity_Id IN ( " . $user["entityId"] . " )";
            }
            else
            {
                $users .= $user["entityId"];
                $userFilter = " AND entity.Entity_Id IN ( " . $users . " )";
            }

        }

        // Check what sexes we need to retrieve
        switch($userPreferences["sex"])
        {
            case 1:
                $userFilter .= " AND entity.Sex = 1";
                break;
            case 2:
                $userFilter .= " AND entity.Sex = 2";
                break;
        }

        // Perform the rest of the binding
        if(empty($data[":entityId"]))
            $data[":entityId"] = $user["entityId"];
        if(empty($data[":currLat"]))
            $data[":currLat"] = $lat;
        if(empty($data[":currLong"]))
            $data[":currLong"] = $long;
        if(empty($data[":radiusCap"]))
            $data[":radiusCap"] = 999999;

        // Bind the values and start the query - for over 18s
        $query = "SELECT DISTINCT entity.Entity_Id,
                                  entity.Fb_Id AS facebook_id,
                                  entity.First_Name AS first_name,
                                  entity.Last_Name AS last_name,
                                  entity.Profile_Pic_Url AS profile_pic,
                                  entity.Last_CheckIn_Country AS last_country,
                                  entity.Last_CheckIn_Dt AS last_checkin_date,
                                  entity.Last_CheckIn_Lat AS last_checkin_lat,
                                  entity.Last_CheckIn_Long AS last_checkin_long,
                                  entity.Last_CheckIn_Place AS last_checkin_place,
                                  entity.Sex,
                                  entity.Score,
                                  entity.Email,
                                  (
                                      SELECT Category FROM friends WHERE Entity_Id1 = :entityId AND Entity_Id2 = entity.Entity_Id
                                      OR Entity_Id2 = :entityId AND Entity_Id1 = :entityId LIMIT 1
                                  ) AS FC,
                                  TIMESTAMPDIFF(YEAR, entity.DOB, NOW()) AS Age,
                                  (6371 * acos( cos( radians(:currLat) ) * cos( radians(entity.Last_CheckIn_Lat) ) * cos( radians(entity.Last_CheckIn_Long) - radians(:currLong) ) + sin( radians(:currLat) ) * sin( radians(entity.Last_CheckIn_Lat) ) ) ) AS distance
                  FROM entity
                      LEFT JOIN setting
                           ON setting.Entity_Id = entity.Entity_Id
                      LEFT JOIN preferences
                           ON preferences.Entity_Id = entity.Entity_Id
                  WHERE
                      entity.Entity_Id = setting.Entity_Id AND
                      entity.Entity_Id != :entityId AND
                      entity.Create_Dt != entity.Last_CheckIn_Dt AND
                      (6371 * acos( cos( radians(:currLat) ) * cos( radians(entity.Last_CheckIn_Lat) ) * cos( radians(entity.Last_CheckIn_Long) - radians(:currLong) ) + sin( radians(:currLat) ) * sin( radians(entity.Last_CheckIn_Lat) ) ) ) < :radiusCap
                  ".$userFilter."
                  GROUP BY entity.Entity_Id
                  ORDER BY distance ASC
                  ";

        // Get the results and build the response payload
        // we set emulate prepares to false and then true here to allow the LIMIT to be bound successfully
        $res = Database::getInstance()->fetchAll($query, $data);

        if(empty($res))
            return Array("error" => "404", "message" => "Sorry, it doesn't look like there are any users near by.  Update your privacy settings to see more users!");
        // Ensure all privacy is taken care of here...i.e. no facebook friends must be returned with no last name
        else
        {
            $users = Array();

            if($limit > count($res))
                $limit = count($res);

            for($i = 0; $i < $limit; $i++)
            {
                $user = $res[$i];

                if(empty($user["FC"]))
                    $user["FC"] = "3";
                if($user["FC"] != 2)
                    $user["last_name"] = "";

                array_push($users, $user);
            }

            return Array("error" => "200", "message" => "Successfully retrieved " . $limit . " users around your location!", "payload" => Array("users" => $users));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Location
    |--------------------------------------------------------------------------
    |
    | Updates the users location on the map with the new lat, long coordinates.
    |
    | @param $args - An array containing all information needed to make this
    |                request.
    |                Params should include: [lat, long, entityId]
    |
    |
    */

    public static function updateLocation($args, $user)
    {
        $query = "UPDATE entity SET last_checkin_lat = :lat, Last_CheckIn_Long = :lng WHERE entity_id = :entityId";
        $data  = Array(
            ":lat"      => $args["lat"],
            ":lng"      => $args["long"],
            ":entityId" => $user["entity_id"]
        );

        if(Database::getInstance()->update($query, $data) > 0)
            return Array('error' => '200', 'message' => 'The resource was updated successfully, your new latitude is '.$args["lat"].' and your longitude is '.$args["long"]);

        return Array("error" => "500", "message" => "An internal error occurred when processing your request. Please try again.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get Favorite Places
    |--------------------------------------------------------------------------
    |
    | Returns a list of all of the favorite places for a user
    |
    | @param $userId - The ID of the user, either Kinekt or FB ID
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function getFavoritePlaces($userId)
    {
        $data = Array(":entity_id" => $userId);
        $query = "SELECT Place_Pic_Url, Like_Id, Entity_Id, Place_Name
                  FROM favorites
                  WHERE favorites.Entity_Id = :entity_id";

        $res = Database::getInstance()->fetchAll($query, $data);
        if(count($res) > 0)
            return $res;
        else
            return Array("error" => "404", "message" => "This user has no favorite places");
    }

    /*
    |--------------------------------------------------------------------------
    | Get All Users
    |--------------------------------------------------------------------------
    |
    | Returns a list of all users
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function getAll()
    {
        $query = "SELECT First_Name, Last_Name, Email, Create_Dt, TIMESTAMPDIFF(YEAR, DOB, CURRENT_DATE) AS age FROM entity GROUP BY Entity_Id ORDER BY Create_Dt DESC";

        $res = Database::getInstance()->fetchAll($query);
        $count = sizeof($res);

        // Ensure we have users
        if($count == 0)
            return Array("error" => "404", "message" => "There are no users in the system");

        // Users were found, send the data
        return Array("error" => "200", "message" => "We currently have {$count} users.", "payload" => $res);
    }

    /*
    |--------------------------------------------------------------------------
    | Get User
    |--------------------------------------------------------------------------
    |
    | Returns a user by their object ID, can either use FB or Kinekt ID
    |
    | @param $userId - The ID for this user
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function get($userId, $user = null)
    {
        // Build the return payload
        $data = Array(":entity_id" => $userId, ":user_id" => $user["entityId"]);
        $query = "SELECT DISTINCT entity.*, friends.Category, preferences.*, setting.*
                  FROM entity
                  LEFT JOIN preferences
                    ON entity.Entity_Id = preferences.Entity_Id
                  LEFT JOIN setting
                    ON entity.Entity_Id = setting.Entity_Id
                  LEFT JOIN friends
                    ON entity.Entity_Id IN (friends.Entity_Id1, friends.Entity_Id2)
                    AND (friends.Entity_Id1 = :user_id OR friends.Entity_Id2 = :user_id)
                  WHERE entity.Entity_Id = :entity_id
                  OR entity.Fb_Id = :entity_id
                  GROUP BY entity.Entity_Id";

        $res = Database::getInstance()->fetch($query, $data);
        if(!empty($res)) {
            $arr = Array(

                "entity_id" => $res->Entity_Id,
                "facebook_id" => $res->Fb_Id,
                "first_name" => $res->First_Name,
                "last_name" => $res->Last_Name,
                "email" => $res->Email,
                "profile_pic" => $res->Profile_Pic_Url,
                "sex" => $res->Sex,
                "DOB" => $res->DOB,
                "about" => $res->About,
                "score" => $res->Score,
                "create_dt" => $res->Create_Dt,
                "last_checkin_lat" => $res->Last_CheckIn_Lat,
                "last_checkin_long" => $res->Last_CheckIn_Long,
                "last_checkin_place" => $res->Last_CheckIn_Place,
                "last_checkin_country" => $res->Last_CheckIn_Country,
                "last_checkin_date" => $res->Last_CheckIn_Dt,
                "score" => $res->Score,
                "score_flag" => $res->Score_Flag,
                "image_urls" => $res->Image_Urls,
                "category" => empty($res->Category) ? 3 : $res->Category,
                "places" => self::getFavoritePlaces($userId),
                "preferences" =>
                    Array(
                        "facebook" => $res->Pref_Facebook,
                        "kinekt" => $res->Pref_Kinekt,
                        "everyone" => $res->Pref_Everyone,
                        "sex" => $res->Pref_Sex,
                        "lower_age" => $res->Pref_Lower_Age,
                        "upper_age" => $res->Pref_Upper_Age,
                        "checkin_expiry" => $res->Pref_Chk_Exp
                    ),
                "settings" =>
                    Array(
                        "privacy_checkin" => $res->Pri_CheckIn,
                        "visibility" => $res->Pri_Visability,
                        "notification_tag" => $res->Notif_Tag,
                        "notification_message" => $res->Notif_Msg,
                        "notification_new_friend" => $res->Notif_New_Friend,
                        "notification_friend_request" => $res->Notif_Friend_Request,
                        "notification_checkin_activity" => $res->Notif_CheckIn_Activity,
                        "list_visibility" => $res->list_visibility
                    )
            );

            // Set the mutual friend key only if this isnt your profile
            if($user != null && $user["entityId"] != $userId)
            {
                $mutualFriends = self::getMutualFriends($user["entityId"], $userId);
                $arr["mutual_friends"] = $mutualFriends;
            }

            return Array("error" => "200", "message" => "Successfully retrieved the user with id: " . $userId, "payload" => $arr);
        }
        else if(empty($res))
            return Array("error" => "404", "message" => "Sorry, the user with id: " . $userId . " does not exist on the server.");
        else
        {
            if(empty($res->Category))
                $res->Category = 3;

            return Array("error" => "200", "message" => "Successfully retrieved the user with id: " . $userId, "payload" => $res);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    |
    | Updates the users profile such as there name, about and profile pics.
    | This will only update on the database if the cached result doesn't match.
    |
    | @param $userId      - The user we are updating info for.
    | @param $data        - The new array of information to set for this user.
    | @param $setResponse - Determines if we echo a response to the client
    |                       when this call succeeds. By default this will happen
    |                       however we don't want this to happen when logging in
    |
    */

    public static function updateProfile($userId, $payload, $user = null)
    {
        if ($user != null && !empty($user["entityId"]) && ($userId != $user["entityId"]))
            return Array('error' => '401', 'message' => 'You are not authorised to access this resource because you are not the user who is updating: tried to update user ' . $userId . ' with logged in user ' . $user["entityId"]);

        // Update old values to keep app working...
        if(!empty($payload["facebook_id"]))
            $payload["fb_id"] = $payload["facebook_id"];

        // Construct an array of valid keys
        $validKeys = Array("first_name", "last_name", "profile_pic_url", "entity_id", "fb_id", "email", "dob", "sex", "about", "create_dt", "last_checkin_lat", "last_checkin_long",
        "last_checkin_place", "last_checkin_dt", "score", "score_flag", "image_urls");

        // Strip the image_urls and profile_pic_url from the payload if this is a login event
        if($user == null && (array_key_exists("profile_pic_url", $payload) || array_key_exists("image_urls", $payload)))
        {
            // IMPLEMENT THIS I
        }

        // We know the user is valid, therefore update it with the new
        // info set in the $data object.  It doesn't matter if we override information here
        $data = Array();
        $query = "UPDATE entity SET ";
        foreach($payload as $k => $v)
        {
            if(in_array($k, $validKeys)) {
                $query .= $k . " = :" . $k . ", \n";
                $data[":" . $k] = $v;
            }
        }
        $query = substr_replace($query, " ", strrpos($query, ","), strlen(","));
        $query .= " WHERE Entity_Id = :entity_id";

        $data[":entity_id"] = $userId;

        // Perform the update on the database
        $res = Database::getInstance()->update($query, $data);
        // Check if we only performed an update call which isn't part of
        if($res != 0)
            return Array('error' => '200', 'message' => 'The user with ID: '.$userId.' was updated successfully.');
        else
            return Array('error' => '203', 'message' => 'The user with ID: '.$userId.' is already up to date and does not need modifying.');
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    |
    | Attempt to log the user into the system, if the account does not
    | exist we create them in the system permitted that they are 18 or older
    |
    */

    public static function login($args)
    {
        // Ensure we have a valid object, if any of the major determinate factors are null then
        // echo a 400 back to the user
        if(empty($args) || empty($args['facebook_id']) || empty($args["device_id"]))
            return Array('error' => '400', 'message' => "Sorry, no data was passed to the server.  Please ensure the user object is sent via JSON in the HTTP body");

        if(empty($args["push_token"]))
            $args["push_token"] = "";

        // Check if the user already exists in the system, if they don't then sign them up to the system
        $userExists = self::get($args['facebook_id']);
        if($userExists["error"] != 404)
        {

            $userExists["payload"] = json_decode(json_encode($userExists["payload"]), true);

            // Ensure we have a valid session
            $token = Session::setSession($userExists["payload"]["entity_id"], $args);

            // Check for users under 18
            if (time() - strtotime($args['dob']) <= 15 * 31536000 || $args['dob'] == null)
                return Array('error' => '400', 'message' => 'Bad request, you must be 16 years or older.');

            // Update the users details, this includes updating the ABOUT info and profile pictures,
            // We do this here as profile info may have changed on Facebook since the last login.
            $response = self::updateProfile($userExists["payload"]["entity_id"], $args);

            if($response["error"] == "400")
                return $response;

            // Check if there are any mutual friends to add - we do that here instead of on sign up as every time
            // we log in to the app more of our friends may have signed up to the app through FB
            if($args["friends"] != "")
                $response = Friend::addFriends($args['friends'], 1, $userExists["payload"]["entity_id"]);

            // Set values so that they are not null
            if(empty($userExists["payload"]["last_checkin_place"]))
                $userExists["payload"]["last_checkin_place"] = "";
            if(empty($userExists["payload"]["last_checkin_country"]))
                $userExists["payload"]["last_checkin_country"] = "";

            // Override the payload as we are logging in, we don't want a list of all friends.
            $response["message"] = "You were logged in successfully, friends may have been updated: " . $response["message"];
            $response["payload"] = Array("entity" => $userExists, "session" => $token);

            return $response;
        }
        else
            return self::signup($args);
    }

    // Get all user emails
    public static function getUserEmails()
    {
        $query = "SELECT email FROM entity GROUP BY Entity_Id";
        $data  = Array();

        $res   = Database::getInstance()->fetchAll($query, $data);
        if(count($res))
        {
            $emailString = "";
            foreach($res as $email)
            {
                $emailString .= $email["email"] . ", ";
            }
            $emailString = rtrim($emailString, ", ");

            return $emailString;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Sign Up
    |--------------------------------------------------------------------------
    |
    | Creates a new user in the database, only if they are 18 or older.
    |
    | @param $args : The JSON payload sent to the server detailing the information
    |                on this user.  An invalid insert will result in a 400 error
    |                being returned to the client.
    |
    | @return $arr : An array containing either an error detailing what went
    |                wrong with the request, or a payload holding all user info
    |                to be manipulated by the client.
    |
    */

    public static function signup($args)
    {
        // Check for users under 18
        if (time() - strtotime($args['dob']) <= 15 * 31536000 || $args['dob'] == null)
            return Array('error' => '401', 'message' => 'Bad request, you must be 16 years or older.');

        // We know our user is old enough, insert the new user.
        $query = "INSERT IGNORE INTO entity(fb_id, first_name, last_name, email, profile_pic_url, sex, dob, about, create_dt, last_checkin_dt, image_urls, score)
                  VALUES(:facebook_id, :firstName, :lastName, :email, :profilePic, :sex, :dob, :about, :createdAt, :lastCheckin, :images, :score)";

        $data  = Array(
            ':facebook_id' => $args['facebook_id'],
            ':firstName'   => $args['first_name'],
            ':lastName'    => $args['last_name'],
            ':email'       => $args['email'],
            ':profilePic'  => $args['profile_pic_url'],
            ':sex'         => $args['sex'],
            ':dob'         => $args['dob'],
            ':about'       => $args['about'],
            ':createdAt'   => date('Y-m-d H:i:s'),
            ':lastCheckin' => date('Y-m-d H:i:s'),
            ':images'      => $args['image_urls'],
            ':score'       => 0
        );

        // Sign the user up and get their ID so we can insert their default preferences
        $id = Database::getInstance()->insert($query, $data);

        // If the user exists, throw an exception.
        if($id == 0)
            return Array('error' => '400', 'message' => "Whoops, there is already an account registered with the Facebook ID: ". $args['facebook_id']);

        // Everything went well, create a session for this user:
        $token = Session::setSession($id, $args);

        // Add all of the friends this user has.
        if($args["friends"] != "")
            Friend::addFriends($args["friends"], 1, $id);

        // Insert the user into the preferences table
        $query = "INSERT INTO preferences(entity_id)
                  VALUES(:entity_id)";

        $data  = Array(':entity_id' => $id);
        Database::getInstance()->insert($query, $data);

        // Set default setting values for the user
        $query = "INSERT INTO setting(Entity_Id, Pri_CheckIn, Pri_Visability, Notif_Tag, Notif_Msg, Notif_New_Friend, Notif_Friend_Request, Notif_CheckIn_Activity)
                  VALUES (:entityId, :a, :b, :c, :d, :e, :f, :g)";

        $data  = Array(":entityId" => $id, ":a" => 2, ":b" => 2, ":c" => 1, ":d" => 1, ":e" => 1, ":f" => 1, ":g" => 1);
        Database::getInstance()->insert($query, $data);

        if($id > 0)
        {
            $user = self::get($id);
            return Array('error' => '200', 'message' => 'The user was created successfully.', 'payload' => Array("entity" => $user, "session" => $token));
        }
        else
            return Array('error' => '500', 'message' => 'There was an internal error when creating the user listed in the payload.  Please try again.', 'payload' => Array('entity_id' => $id, 'entity_data' => $args));
    }

    /*
     |--------------------------------------------------------------------------
     | Sign Out
     |--------------------------------------------------------------------------
     |
     | Destroys the current session and signs the user out on this device.
     |
     */

    public static function signOut($userId, $user)
    {
        if($userId != $user["entityId"])
            return Array("error" => "401", "message" => "Failed to log out: User with id " . $user["entityId"] . " tried to sign user with id " . $userId . " out, try again with /api/v2/User/sign-out/" . $user["entityId"]);

        // Delete the session from the sessions table
        $query = "DELETE FROM user_sessions WHERE oid = :user_id AND device = :device_id";
        $data  = Array(":user_id" => $userId, ":device_id" => $user["deviceId"]);

        $res   = Database::getInstance()->delete($query, $data);
        if($res != 1)
            return Array("error" => "400", "message" => "Failed to log the user out from the system, please try again");

        return Array("error" => "200", "message" => "logged the user with ID " . $userId . " out successfully.");
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
        // Check for an invalid match
        if(($userId <= 0 || $friendId <= 0) || $userId == $friendId)
            return "No mutual friends";

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

        $res = Database::getInstance()->fetchAll($query, $data);
        if(count($res) == 0)
            return "No mutual friends.";
        else
            return $res;
    }

    /*
     |--------------------------------------------------------------------------
     | GET PREFERENCES
     |--------------------------------------------------------------------------
     |
     | Get the users preferences.
     |
     | @param userId - The identifier of the user.
     |
     | @return       - A payload with the settings of the user.
     |
     */

    public static function getPreferences($userId)
    {
        $query = "SELECT DISTINCT Pref_Chk_Exp,Pref_Facebook, Pref_Kinekt,
                                  Pref_Everyone,Pref_Sex, Pref_Lower_Age,Pref_Upper_Age
                  FROM  preferences
                  WHERE Entity_Id = :userId";

        $data = Array(":userId" => $userId);

        $res = Database::getInstance()->fetch($query, $data);

        if(!empty($res))
            return Array("error" => "200", "message" => "Successfully retrieved the users preferences.", "payload" => $res);
        else
            return Array("error" => "409", "message" => "Conflict: no user matches identifier provided.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET SETTINGS
     |--------------------------------------------------------------------------
     |
     | Get the settings of a user.
     |
     | @param userId - The identifier of the user.
     |
     | @return       - A payload with the settings of the user.
     |
     */

    public static function getSettings($userId)
    {
        $query = "SELECT DISTINCT Pri_CheckIn, Pri_Visability, Notif_Tag,Notif_Msg,
                                  Notif_New_Friend,Notif_Friend_Request, Notif_CheckIn_Activity, list_visibility
                  FROM  setting
                  WHERE Entity_Id = :userId";

        $data = Array(":userId" => $userId);

        $res = Database::getInstance()->fetch($query, $data);

        if(!empty($res))
            return Array("error" => "200", "message" => "Successfully retrieved the users settings.", "payload" => $res);
        else
            return Array("error" => "409", "message" => "Conflict: no user matches identifier provided.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET NOTIFICATIONS
     |--------------------------------------------------------------------------
     |
     | Get all of the notifications for the user.
     |
     | @header $session_token - The token for the session.
     |
     | @header $device_id     - The identifier of the device.
     |
     | @return                - A payload of notifications
     |
     */

    public static function getNotifications($userId)
    {
        $query = "SELECT notifications.*, entity.First_Name, entity.Last_Name, entity.Profile_Pic_Url, entity.Sex
                  FROM notifications
                  JOIN entity
                    ON notifications.sender = entity.Entity_Id
                  WHERE receiver = :entityId
                  ORDER BY notif_dt DESC";
        $data  = Array(":entityId" => $userId);

        $res   = Database::getInstance()->fetchAll($query, $data);
        if(count($res) == 0)
            return Array("error" => "404", "message" => "No new notifications.");
        else
            return Array("error" => "200", "message" => "You have " . count($res) . " new notifications!", "payload" => $res);
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE PREFERENCES
     |--------------------------------------------------------------------------
     |
     | Update the preferences for the user. Requires at least one of the values of the
     | settings table to be sent in the JSON body, else an error is returned.
     |
     | @param $payload - The JSON encoded body for the PUT HTTP request.
     |                   Requires at least one of the following values to be specified:
     |                   1) Pref_Facebook
     |                   2) Pref_Kinekt
     |                   3) Pref_Everyone
     |                   4) Pref_Sex
     |                   5) Pref_Lower_Age
     |                   6) Pref_Upper_Age
     |                   7) Pref_Chk_Exp
     |
     | @param $userId  - The identifer of the user.
     |
     */

    public static function updatePreferences($payload, $userId, $user)
    {
        if($userId != $user['entityId'])
            return Array("error" => "401", "message" => "Un-Authorised.  The user requesting this resource does not match the entity that this resource belongs to, please login with the correct account and try again.", "payload" => "");

        // Check to see what JSON body values we have.
        $foundSettings = Array();

        // Check if values exist, and if so add to an array of values using the names of the columns from the database.
        if(isset($payload['Pref_Facebook']))   $foundSettings['Pref_Facebook']    = $payload['Pref_Facebook'];
        if(isset($payload['Pref_Kinekt']))     $foundSettings['Pref_Kinekt']      = $payload['Pref_Kinekt'];
        if(isset($payload['Pref_Everyone']))   $foundSettings['Pref_Everyone']    = $payload['Pref_Everyone'];
        if(isset($payload['Pref_Sex']))        $foundSettings['Pref_Sex']         = $payload['Pref_Sex'];
        if(isset($payload['Pref_Lower_Age']))  $foundSettings['Pref_Lower_Age']   = $payload['Pref_Lower_Age'];
        if(isset($payload['Pref_Upper_Age']))  $foundSettings['Pref_Upper_Age']   = $payload['Pref_Upper_Age'];
        if(isset($payload['Pref_Chk_Exp']))    $foundSettings['Pref_Chk_Exp']     = $payload['Pref_Chk_Exp'];

        // If we have found no matching values, then return an error.
        if(count($foundSettings) == 0)
            return Array("error" => "400", "message" => "Updating has failed because at least one preferences tag needs to be provided, please check your input payload as it is case sensitive.  i.e. Pref_Facebook is not the same as pref_facebook");

        // Create the variable that will form the query for the SQL update operation.
        // Note: it is meant to be 'update settings set ', so ignore red error warning.
        $query = 'UPDATE preferences
                  SET';

        // For each tag we have, add column name and value.
        // Count keeps track of the index of the foreach. When the index is equal to the size
        // of the array of values, we don't include a comma and then move on to the WHERE clause
        // of the query.
        $data = Array();
        $count = 0;
        foreach($foundSettings as $k => $v)
        {
            // Find tag name and get value from array. Concatenate both together.
            $query .= ' ' . $k . ' = ' . ":" . $k;

            // Bind the tag and value.
            $data[":" . $k] = $v;

            // If we exhaust all values, don't include a comma at the end of the query.
            if($count == count($foundSettings) -1){} // Don't include.
            else
                $query .= ', ';                      // Include.

            $count++;
        }

        // Finally add the WHERE clause and do the normal stuff of appending the userId.
        // Example output: UPDATE setting
        //                 SET Pri_Visability = 2, Notif_New_Friend = 1, Notif_CheckIn_Activity = 0
        //                 WHERE Entity_Id = :userId
        $query .= ' WHERE Entity_Id = :userId';

        // Bind user parameter to the query.
        $data[":userId"] = $user['entityId'];

        // Perform the update
        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "The preferences have been changed successfully.");
        else
            // If the values of the settings are the same as before the query was performed.
            // Let me know if you want this check done above - Adam.
            return Array("error" => "409", "message" => "The values could not be changed as they match existing values in the system.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE SETTINGS
     |--------------------------------------------------------------------------
     |
     | Update the settings for the user. Requires at least one of the values of the
     | settings table to be sent in the JSON body, else an error is returned.
     |
     | @param $payload - The JSON encoded body for the PUT HTTP request.
     |                   Requires at least one of the following values to be specified:
     |                   1) privacyCheckin
     |                   2) privacyVisibility
     |                   3) notifTag
     |                   4) notifMessages
     |                   5) notifNewFriends
     |                   6) notifFriendRequests
     |                   7) notifCheckinActivity
     |                   8) listVisibility
     |
     | @param $userId  - The identifer of the user.
     |
     |
     */

    public static function updateSettings($payload, $userId, $user)
    {
        if($userId != $user['entityId'])
            return Array("error" => "401", "message" => "Bad request. Somehow the entity_id of the validated session does not match the identifier".
                " of the sent user provided by the parameter.", "payload" => "");

        // Check to see what JSON body values we have.
        $foundSettings = Array();

        // Check if values exist, and if so add to an array of values using the names of the columns from the database.
        if(isset($payload['privacyCheckin']))       $foundSettings['Pri_CheckIn']            = $payload['privacyCheckin'];
        if(isset($payload['privacyVisibility']))    $foundSettings['Pri_Visability']         = $payload['privacyVisibility'];
        if(isset($payload['notifTag']))             $foundSettings['Notif_Tag']              = $payload['notifTag'];
        if(isset($payload['notifMessages']))        $foundSettings['Notif_Msg']              = $payload['notifMessages'];
        if(isset($payload['notifNewFriends']))      $foundSettings['Notif_New_Friend']       = $payload['notifNewFriends'];
        if(isset($payload['notifFriendRequests']))  $foundSettings['Notif_Friend_Request']   = $payload['notifFriendRequests'];
        if(isset($payload['notifCheckinActivity'])) $foundSettings['Notif_CheckIn_Activity'] = $payload['notifCheckinActivity'];
        if(isset($payload['listVisibility']))       $foundSettings['list_visibility']        = $payload['listVisibility'];

        // If we have found no matching values, then return an error.
        if(count($foundSettings) == 0)
            return Array("error" => "400", "message" => "Updating has failed because at least one settings tag needs to be provided.");

        // Create the variable that will form the query for the SQL update operation.
        // Note: it is meant to be 'update settings set ', so ignore red error warning.
        $query = 'UPDATE setting
                  SET';

        // For each tag we have, add column name and value.
        // Count keeps track of the index of the foreach. When the index is equal to the size
        // of the array of values, we don't include a comma and then move on to the WHERE clause
        // of the query.
        $data = Array();
        $count = 0;
        foreach($foundSettings as $k => $v)
        {
            // Find tag name and get value from array. Concatenate both together.
            $query .= ' ' . $k . ' = ' . ":" . $k;

            // Bind the tag and value.
            $data[":" . $k] = $v;

            // If we exhaust all values, don't include a comma at the end of the query.
            if($count == count($foundSettings) -1){} // Don't include.
            else
                $query .= ', ';                      // Include.

            $count++;
        }

        // Finally add the WHERE clause and do the normal stuff of appending the userId.
        // Example output: UPDATE setting
        //                 SET Pri_Visability = 2, Notif_New_Friend = 1, Notif_CheckIn_Activity = 0
        //                 WHERE Entity_Id = :userId
        $query .= ' WHERE Entity_Id = :userId';

        // Bind user parameter to the query.
        $data[":userId"] = $user['entityId'];

        // Perform the update
        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "The settings have been changed successfully.");
        else
            // If the values of the settings are the same as before the query was performed.
            // Let me know if you want this check done above - Adam.
            return Array("error" => "203", "message" => "Please select new values for the chosen settings as the values in the system match the ones you just provided.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE SCORE
     |--------------------------------------------------------------------------
     |
     | Update a users score to reflect the changes they have made whilst using one or more of the apps.
     | The score needs to be updated on the apps end. I suggest using the scoreValue returned and performing
     | validation based on the HTTP response; update UI with returned value.
     |
     | @param $payload    - The Json encoded information for the HTTP PUT request.
     |
     | @param $scoreValue - The value to add to the score value in the database.
     |
     | @return            - The new value of the score with a success message, else a failure message.
     |
     */
    public static function updateScore($payload, $scoreValue, $user)
    {
        $query = "UPDATE entity
                  SET    Score = Score + :scoreValue
                  WHERE  Entity_Id = :userId
                 ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":scoreValue" => $scoreValue);

        // Perform the update
        if (Database::getInstance()->update($query, $data))
            return Array("error" => "200", "message" => "The score has been successfully updated.", "payload" => Array("scoreValue" => $scoreValue));
        else
            return Array("error" => "400", "message" => "There has been an error updating the score for this user. ");

    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ADD FAVOURITE
     |--------------------------------------------------------------------------
     |
     | Add a new favourite place. The placeName and imageUrl need to be sent
     | in the body of the HTTP POST request.
     |
     | @param $userId   - The identifier of the user that is adding the new place.
     |
     | @param $payload  - The JSON encoded body for the HTTP POST request.
     |
     | @body $placeName - The name of the place.
     |
     | @body $picUrl    - The url link for the place.
     |
     | @return          - A success or failure message dependent on the outcome.
     |
     */

    public static function addFavourite($payload, $userId, $user)
    {
        if($userId != $user['entityId'])
            return Array("error" => "401", "message" => "Bad request. Somehow the entity_id of the validated session does not match the identifier".
                " of the sent user provided by the parameter.", "payload" => "");

        // Checks to see whether the user combination already exists in the database.
        $query = "INSERT INTO favorites(Entity_Id, Place_Name, Place_Pic_Url)
                          SELECT :userId, :placeName, :picUrl
                          FROM DUAL
                          WHERE NOT EXISTS
                          (
                              SELECT favorites.Like_Id FROM favorites
                              WHERE (Entity_Id = :userId AND Place_Name = :placeName AND Place_Pic_Url = :picUrl)
                          )
                          LIMIT 1
                          ";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":placeName" => $payload['placeName'], ":picUrl" => $payload['picUrl']);

        // Perform the insert, then increment count if this wasn't a duplicate record
        if (Database::getInstance()->insert($query, $data))
            return Array("error" => "200", "message" => "" . $payload['placeName'] . " has been added to your favourites.");
        else
            return Array("error" => "409", "message" => "This place is already in your favourites list.");
    }

    /*
     |--------------------------------------------------------------------------
     | DELETE ACCOUNT
     |--------------------------------------------------------------------------
     |
     | Delete all information within the database relating to a user.
     |
     | @param $userId - The identifier of the user.
     |
     | @return        - A failure or success message depending on whether or not more than one
     |                  row from a table relating to a user was deleted.
     |
     */

    public static function deleteAccount($payload, $userId, $user)
    {
        if($userId != $user['entityId'])
            return Array("error" => "401", "message" => "Bad request. You attempted to delete an account which was not yours. Try again.", "payload" => "");

        // Bind the parameters to the query - do this at the top level because it will be used a lot below.
        $data = Array(":userId" => $user['entityId']);

        // How many rows have been affected.
        $deletedCount = 0;
        $toDelete     = 0;

        // User sessions
        $query = "DELETE FROM user_sessions
                  WHERE oid = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Favourites
        $query = "DELETE FROM favorites
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Friends
        $query = "DELETE FROM friends
                  WHERE Entity_Id1 = :userId
                  OR    Entity_Id2 = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Chat messages
        $query = "DELETE FROM chatmessages
                  WHERE sender   = :userId
                  OR    receiver = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkins
        $query = "DELETE FROM checkins
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkin comments
        $query = "DELETE FROM checkin_comments
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkin likes
        $query = "DELETE FROM checkin_likes
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Friend requests
        $query = "DELETE FROM friend_requests
                  WHERE Req_Receiver = :userId
                  OR    Req_Sender   = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Entity - the user
        $query = "DELETE FROM entity
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Notifications
        $query = "DELETE FROM notifications
                  WHERE sender   = :userId
                  OR    receiver = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Preferences
        $query = "DELETE FROM preferences
                  WHERE Entity_Id = :userId
                  ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Settings
        $query = "DELETE FROM setting
                  WHERE Entity_Id = :userId
                 ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Settings
        $query = "DELETE FROM checkinArchive
                  WHERE entityId = :userId
                 ";

        $toDelete++;
        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        if($deletedCount > 0)
            return Array("error" => "200", "message" => "The user account has been deleted. ". $deletedCount ."/".$toDelete.": rows were affected.");
        else
            return Array("error" => "400", "message" => "The operation was successful, but no rows were affected.");
    }

    /*
     |--------------------------------------------------------------------------
     | DELETE FAVOURITE PLACE
     |--------------------------------------------------------------------------
     |
     | Remove a favourite place of a user.
     |
     | @param $likeId - the identifier of the favourite place.
     |
     | @return        - A success or failure message dependent on whether the place was
     |                  deleted or not.
     |
     */

    public static function removeFavourite($likeId, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // User sessions
        $query = "DELETE FROM favorites
                  WHERE Entity_Id = :userId
                  AND   Like_Id   = :likeId
                  ";

        // Bind the parameters to the query - do this at the top level because it will be used a lot below.
        $data = Array(":userId" => $user['entityId'], ":likeId" => $likeId);

        // Remove from favourites.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "This place has been successfully removed from your favourites.");
        else
            return Array("error" => "409", "message" => "Could not remove the favourite as it does not belong to you.");
    }


}