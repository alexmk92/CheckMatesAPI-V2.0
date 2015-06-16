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
| @author - Alex Sims (Checkmates CTO)
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

    public static function getUsersAtLocation($lat, $long, $radius, $limit)
    {
        $DB = Database::getInstance();

        $data = Array(":lat" => $lat, ":long" => $long, "radius" => $radius, "limit" => $limit);

        $query = "SELECT * FROM entity WHERE entity_id = :entity_id";

        return $DB->fetchAll($query, $data);
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

    public static function updateLocation($args)
    {
        if(empty($args["session_token"]) || empty($args["device_id"]))
            return Array('error' => '401', 'message' => "Un-Authorised: The resource you're trying to update does not match your session token.");

        $user = Session::validateSession($args["session_token"], $args["device_id"]);

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
        $query = "SELECT * FROM entity WHERE entity_id = :entity_id OR fb_id = :entity_id";

        $res = Database::getInstance()->fetch($query, $data);

        if(empty($res))
            return Array("error" => "404", "message" => "Sorry, the user with id: " . $userId . " does not exist on the server.");

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
        if($args == null || !isset($args['facebook_id']) || $args['facebook_id'] == '' || !isset($args['device_id']) || !isset($args['push_token']))
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
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getPreferences($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET SETTINGS
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getSettings($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET NOTIFICATIONS
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getNotifications($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET RECENT USER
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getRecentUser($args)
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
     | TODO: ADD DESCRIPTION
     |
     */

    public static function updateSettings($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (PUT) UPDATE SCORE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */
    private static function updateScore($entityId, $amount, $operator)
    {

    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ADD FAVOURITE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function addFavourite($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | DELETE ACCOUNT
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function deleteAccount($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | DELETE FAVOURITE PLACE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function removeFavourite($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }


}