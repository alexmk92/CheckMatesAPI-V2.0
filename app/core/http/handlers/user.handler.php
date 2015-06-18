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

    public static function getUsersAtLocation($long, $lat, $user)
    {
        // Bind the values and start the query
        $data = Array(":latitude" => $lat, ":longitude" => $long);
        $query = "SELECT DISTINCT entity.Entity_Id,
                                  entity.First_Name AS first_name,
                                  entity.Last_Name AS last_name,
                                  entity.Profile_Pic_Url AS profilePic,
                                  checkinArchive.placeLat AS latitude,
                                  checkinArchive.placeLng AS longitude,
                                  checkinArchive.placeName
                  FROM entity
                  JOIN checkinArchive
                    ON checkinArchive.entityId = entity.Entity_Id
                  WHERE placeLat = :latitude
                  AND   placeLng = :longitude
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
            return Array('error' => '203', 'message' => 'The resource was updated successfully, your new latitude is '.$args["lat"].' and your longitude is '.$args["long"]);

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
        $DB = Database::getInstance();

        $data = Array(":entity_id" => $userId);
        $query = "SELECT * FROM favorites WHERE entity_id = :entity_id OR fb_id = :entity_id";

        return $DB->fetchAll($query, $data);
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
        $query = "SELECT * FROM entity";

        $res = Database::getInstance()->fetchAll($query);
        $count = sizeof($res);

        // Ensure we have users
        if($count == 0)
            return Array("error" => "404", "message" => "There are no users in the system");

        // Users were found, send the data
        return Array("error" => "200", "message" => "Successfully retrieved all {$count} users.", "payload" => $res);
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

    public static function get($userId)
    {
        $data = Array(":entity_id" => $userId);
        $query = "SELECT DISTINCT entity.*, friends.Category
                  FROM entity
                  JOIN friends
                  ON entity.Entity_Id = friends.Entity_Id1 OR entity.Entity_Id = friends.Entity_Id2
                  WHERE entity_id = :entity_id
                  OR fb_id = :entity_id
                  AND friends.Category != 4";

        $res = Database::getInstance()->fetch($query, $data);

        // Attempt to get a user without a friend connection
        if(empty($res))
        {
            $query = "SELECT DISTINCT entity.*
                      FROM entity
                      WHERE entity_id = :entity_id
                      OR fb_id = :entity_id";

            $res = Database::getInstance()->fetch($query, $data);
        }
        if(empty($res))
            return Array("error" => "404", "message" => "Sorry, the user with id: " . $userId . " does not exist on the server.");

        if(empty($res->Category))
            $res->Category = 3;

        return Array("error" => "200", "message" => "Successfully retrieved the user with id: " . $userId, "payload" => $res);
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

    public static function updateProfile($userId, $data)
    {
        // Check for users under 18
        if (time() - strtotime($data['dob']) <= 18 * 31536000 || $data['dob'] == null)
            return Array('error' => '400', 'message' => 'Bad request, you must be 18 years or older.');

        // We know the user is valid, therefore update it with the new
        // info set in the $data object.  It doesn't matter if we override information here
        $query = "UPDATE entity
                  SET    first_name      = :firstName,
                         last_name       = :lastName,
                         email           = :email,
                         sex             = :sex,
                         dob             = :dob,
                         image_urls      = :images,
                         profile_pic_url = :profilePic
                  WHERE  entity_id       = :entId";

        $data  = Array(":firstName"  => $data['first_name'],
                       ":lastName"   => $data['last_name'],
                       ":email"      => $data['email'],
                       ":sex"        => $data['sex'],
                       ":dob"        => $data['dob'],
                       ":images"     => $data['images'],
                       ":entId"      => $userId,
                       ":profilePic" => $data['pic_url']);

        // Perform the update on the database
        $res = Database::getInstance()->update($query, $data);

        // Check if we only performed an update call which isn't part of
        if($res != 0)
            return Array('error' => '200', 'message' => 'The user with ID: '.$userId.' was updated successfully.');
        else
            return Array('error' => '200', 'message' => 'The user with ID: '.$userId.' is already up to date and does not need modifying.');
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
        if(empty($args) || empty($args['facebook_id']) || empty($args['push_token']) || empty($args["device_id"]))
            return Array('error' => '400', 'message' => "Sorry, no data was passed to the server.  Please ensure the user object is sent via JSON in the HTTP body");

        // Check if the user already exists in the system, if they don't then sign them up to the system
        $userExists = self::get($args['facebook_id']);
        if($userExists["error"] != 404)
        {
            $userExists["payload"] = json_decode(json_encode($userExists["payload"]), true);

            // Ensure we have a valid session
            $token = Session::setSession($userExists["payload"]["Entity_Id"], $args);

            // Update the users details, this includes updating the ABOUT info and profile pictures,
            // We do this here as profile info may have changed on Facebook since the last login.
            $response = self::updateProfile($userExists["payload"]["Entity_Id"], $args);

            if($response["error"] == "400")
                return $response;

            // Check if there are any mutual friends to add - we do that here instead of on sign up as every time
            // we log in to the app more of our friends may have signed up to the app through FB
            if($args["friends"] != "")
                $response = Friend::addFriends($args['friends'], '1', $userExists["payload"]["Entity_Id"]);

            // Override the payload as we are logging in, we don't want a list of all friends.
            $response["message"] = "You were logged in successfully, friends may have been updated: " . $response["message"];
            $response["payload"] = Array("entity" => $userExists, "session" => $token);

            return $response;
        }
        else
            return self::signup($args);
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
        if (time() - strtotime($args['dob']) <= 18 * 31536000 || $args['dob'] == null)
            return Array('error' => '400', 'message' => 'Bad request, you must be 18 years or older.');

        // Check if the user already exists
        $query = "SELECT fb_id FROM entity WHERE fb_id = :facebook_id";
        $data  = Array(":facebook_id" => $args['facebook_id']);

        // We know our user is old enough, insert the new user.
        $query = "INSERT IGNORE INTO entity(fb_id, first_name, last_name, email, profile_pic_url, sex, dob, about, create_dt, last_checkin_dt, image_urls, score)
                  VALUES(:facebook_id, :firstName, :lastName, :email, :profilePic, :sex, :dob, :about, :createdAt, :lastCheckin, :images, :score)";

        $data  = Array(
            ':facebook_id' => $args['facebook_id'],
            ':firstName'   => $args['first_name'],
            ':lastName'    => $args['last_name'],
            ':email'       => $args['email'],
            ':profilePic'  => $args['pic_url'],
            ':sex'         => $args['sex'],
            ':dob'         => $args['dob'],
            ':about'       => $args['about'],
            ':createdAt'   => date('Y-m-d H:i:s'),
            ':lastCheckin' => date('Y-m-d H:i:s'),
            ':images'      => $args['images'],
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

        $data  = Array(":entityId" => $id, ":a" => 1, ":b" => 3, ":c" => 1, ":d" => 1, ":e" => 1, ":f" => 1, ":g" => 1);
        Database::getInstance()->insert($query, $data);

        if($id > 0)
            return Array('error' => '200', 'message' => 'The user was created successfully.', 'payload' => Array('entity_id' => $id, 'entity_data' => $args, "session" => $token));
        else
            return Array('error' => '500', 'message' => 'There was an internal error when creating the user listed in the payload.  Please try again.', 'payload' => Array('entity_id' => $id, 'entity_data' => $args));
    }

    /*
     |--------------------------------------------------------------------------
     | GET LISTS
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getLists($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET SCORES
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getScore($args)
    {


        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET PROFILE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getProfile($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
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
                                  Notif_New_Friend,Notif_Friend_Request, Notif_CheckIn_Activity
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


        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE FAVOURITE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function updateFavourite($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE PREFERENCES
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function updatePreferences($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
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
     |
     | @param $userId  - The identifer of the user.
     |
     |
     */

    public static function updateSettings($payload, $userId, $user)
    {
        if($userId != $user['entityId'])
            return Array("error" => "400", "message" => "Bad request. Somehow the entity_id of the validated session does not match the identifier".
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
        foreach($foundSettings as $setting)
        {
            // Find tag name and get value from array. Concatenate both together.
            $tagName = array_search($setting, $foundSettings);
            $query .= ' ' . $tagName . ' = ' . ":" . $tagName;

            // Bind the tag and value.
            $data[":" . $tagName] = $foundSettings[$tagName];

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
            return Array("error" => "409", "message" => "Please select new values for the chosen settings.");
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
            return Array("error" => "400", "message" => "Bad request. Somehow the entity_id of the validated session does not match the identifier".
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
            return Array("error" => "400", "message" => "Bad request. Somehow the entity_id of the validated session does not match the identifier".
                                                    " of the sent user provided by the parameter.", "payload" => "");

        // Bind the parameters to the query - do this at the top level because it will be used a lot below.
        $data = Array(":userId" => $user['entityId']);

        // How many rows have been affected.
        $deletedCount = 0;

        // User sessions
        $query = "DELETE FROM user_sessions
                  WHERE oid = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Favourites
        $query = "DELETE FROM favorites
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Friends
        $query = "DELETE FROM friends
                  WHERE Entity_Id1 = :userId
                  OR    Entity_Id2 = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Chat messages
        $query = "DELETE FROM chatmessages
                  WHERE sender   = :userId
                  OR    receiver = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkins
        $query = "DELETE FROM checkins
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkin comments
        $query = "DELETE FROM checkin_comments
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Checkin likes
        $query = "DELETE FROM checkin_likes
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Friend requests
        $query = "DELETE FROM friend_requests
                  WHERE Req_Receiver = :userId
                  OR    Req_Sender   = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Entity - the user
        $query = "DELETE FROM entity
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Notifications
        $query = "DELETE FROM notifications
                  WHERE sender   = :userId
                  OR    receiver = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Preferences
        $query = "DELETE FROM preferences
                  WHERE Entity_Id = :userId
                  ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        // Settings
        $query = "DELETE FROM setting
                  WHERE Entity_Id = :userId
                 ";

        if (Database::getInstance()->delete($query, $data))
            $deletedCount++;

        if($deletedCount > 0)
            return Array("error" => "200", "message" => "The user account has been deleted. ". $deletedCount .": rows were affected.");
        else
            return Array("error" => "200", "message" => "The operation was successful, but no rows were affected.");
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

    public static function removeFavourite($likeId, $payload, $user)
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

            // Favourite place has been removed.
            return Array("error" => "200", "message" => "This place has been successfully removed from your favourites.");
        else

            // Conflict in terms of the primary key for the favourites table.
            return Array("error" => "409", "message" => "Conflict: The specified identifier for the favourite place has not matched"
                                                       ." a record in the database.");
    }


}