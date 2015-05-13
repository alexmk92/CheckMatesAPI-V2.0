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

// Include the token object, this is responsible for secure sessions.
require "./app/core/models/ManageToken.php";

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
    | Get Friends
    |--------------------------------------------------------------------------
    |
    | Returns an array of all friends that the user has.
    |
    | @param $userId - The ID for this user (must be kinekt ID)
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function getFriends($userId)
    {
        $DB = Database::getInstance();

        $data = Array(":entity_id" => $userId);
        $query = "SELECT fid, entity_id1, entity_id2, category
                  FROM friends
                  WHERE Entity_Id1 = :entity_id
                  OR Entity_Id2 = :entity_id";

        return $DB->fetchAll($query, $data);
    }

    /*
    |--------------------------------------------------------------------------
    | Add Friends
    |--------------------------------------------------------------------------
    |
    | Adds the friends in the given array to the database for this user.
    |
    | @param $userId - The ID for this user (must be kinekt ID)
    |
    | @return $data - The JSON encoded array containing all results from the
    |                 query.
    |
    */

    public static function addFriends($friends, $category)
    {
        // Creat an array of unique friends
        $friends = array_filter(array_unique(explode(",", $friends)));
        foreach ($friends as $friend)
        {
            // Insert the friend, INSERT IGNORE will ensure only a unique user is inserted
            $query = "INSERT IGNORE INTO friends(entity_id1, entity_id2, category) VALUES(:entityA, :entityB, :category)";
            $data = Array(":entityA" => , ":entityB" =>, ":category" => $category);
        }

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
        return $requests;

        $query = "SELECT * FROM ";
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
        $DB = Database::getInstance();

        $query = "SELECT * FROM entity";

        return $DB->fetchAll($query);
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
        $DB = Database::getInstance();

        $data = Array(":entity_id" => $userId);
        $query = "SELECT * FROM entity WHERE entity_id = :entity_id OR fb_id = :entity_id";

        return $DB->fetch($query, $data);
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
        // Ensure we have a valid object, if any of the major determinent factors are null then
        // echo a 400 back to the user
        if($args == null || $args['ent_fbid'] == '' || $args['ent_dev_id'] == null || $args['ent_push_token'] == null)
            return Array('error' => '400', 'message' => "Sorry, no data was passed to the server.  Please ensure the user object is sent via JSON in the HTTP body");

        // Create a new token
        //$token = new \ManageToken();

        // Check if the user already exists in the system, if they don't then sign them up to the system
        $userExists = self::get($args['ent_fbid']);

        if($userExists != false)
        {
            // Check if there are any mutual friends to add - we do that here instead of on sign up as every time
            // we log in to the app more of our friends may have signed up to Facebook
            self::addFriends($args['ent_friend']);
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
        if (time() - strtotime($args['ent_dob']) <= 18 * 31536000 || $args['ent_dob'] == null)
            return Array('error' => '400', 'message' => 'Bad request, you must be 18 years or older.');

        // Check if the user already exists
        $query = "SELECT fb_id FROM entity WHERE fb_id = :fbId";
        $data  = Array(":fbId" => $args['ent_fbid']);

        // We know our user is old enough, insert the new user.
        $query = "INSERT IGNORE INTO entity(fb_id, first_name, last_name, email, profile_pic_url, sex, dob, about, create_dt, last_checkin_dt, image_urls, score)
                  VALUES(:fbId, :firstName, :lastName, :email, :profilePic, :sex, :dob, :about, :createdAt, :lastCheckin, :images, :score)";

        $data  = Array(
            ':fbId'        => $args['ent_fbid'],
            ':firstName'   => $args['ent_first_name'],
            ':lastName'    => $args['ent_last_name'],
            ':email'       => $args['ent_email'],
            ':profilePic'  => $args['ent_pic_url'],
            ':sex'         => $args['ent_sex'],
            ':dob'         => $args['ent_dob'],
            ':about'       => $args['ent_about'],
            ':createdAt'   => date('Y-m-d H:i:s'),
            ':lastCheckin' => date('Y-m-d H:i:s'),
            ':images'      => $args['ent_images'],
            ':score'       => 0
        );

        // Sign the user up and get their ID so we can insert their default preferences
        $id = Database::getInstance()->insert($query, $data);

        // If the user exists, throw an exception.
        if($id == 0)
            return Array('error' => '400', 'message' => "Whoops, there is already an account registered with the Facebook ID: ". $args['ent_fbid']);

        // Insert the user into the preferences table
        $query = "INSERT INTO preferences(entity_id)
                  VALUES(:entity_id)";

        $data  = Array(':entity_id' => $id);
        Database::getInstance()->insert($query, $data);

        return Array('error' => '200', 'message' => 'The user was created successfully.', 'payload' => Array('entity_id' => $id, 'entity_data' => $args));
    }
}