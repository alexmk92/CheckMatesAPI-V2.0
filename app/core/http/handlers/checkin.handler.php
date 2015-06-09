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
| @author - Alex Sims (Checkmates CTO)
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

    public function getCheckins($args)
    {
        // Check if a valid payload was sent
        if(empty($args["curr_lat"]) || empty($args["curr_long"]))
            return Array("error" => "400", "message" => "Bad request, no latitude or longitude was sent");

        // Check login credentials were sent
        if(empty($args["session_token"]) || empty($args["device_id"]))
            return Array("error" => "401", "message" => "Unauthorised: No session token was provided in the payload.");

        // Validate the session and ensure that the session was set, if not return a 401
        $user = Session::validateSession($args["session_token"], $args["device_id"]);
        if($user["error"] != 200)
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
                          Pref_Upper_Age                  AS upperAge

                  FROM entity
                  JOIN preferences
                    ON preferences.Entity_Id = entity.Entity_Id
                  JOIN setting
                    ON setting.Entity_Id = entity.Entity_Id
                  WHERE entity.Entity_id = :entityId";

        $data = Array(":entityId" => $args["entityId"]);

        $userPreferences = Database::getInstance()->fetch($query, $data);


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

    public static function createCheckin($args)
    {
        // Extract the data from the payload
        $image = $args["image"]["image"];
        $args  = $args["args"];

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

        // Ensure a valid session token has been provided
        $token = Session::validateSession($args["session_token"], $args["device_id"]);

        // If there was an error, then return the bad response object
        if(array_key_exists("error", $token))
            return $token;

        // Check all required fields are set
        if(empty($args["place_name"]) || empty($args["place_lat"]) || empty($args["place_long"]) || empty($args["place_country"]) || empty($args["place_people"]))
            return Array("error" => "400", "message" => "The checkin provided must have its [place_name], [place_long], [place_lat], [place_country] and [place_people] set, check the data you sent in the payload.", "payload" => $args);

        // Format unset fields for the checkin object
        if(empty($args["message"]))
            $args["message"] = "";
        if(empty($args["place_category"]))
            $args["place_category"] = "";
        if(empty($args["place_url"]))
            $args["place_url"] = "";
        if(empty($args["tagged_users"]))
            $args["tagged_users"] = Array();

        // Set the place badge image.
        if(!empty($args["cat_id"]))
        {
            $file_to_open = './public/img/placeBadges/' . $args["cat_id"] . ".png";
            if(file_exists($file_to_open)) {
                $placePic = TRUE;
            }
            else {
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
            $res = self::uploadImage($image);
            if($res["error"] == 400)
                return $res;
            else
                $checkinImageURL = $res["imageUrl"];
        }

        // Everything has succeeded so far, proceed to do scoring here...
        $query = "UPDATE entity SET score = Score + 10 WHERE entity_id = :entityId";
        $data  = Array(":entityId" => $token["entityId"]);

        Database::getInstance()->update($query, $data);

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
            $query = "
                    INSERT INTO checkinArchive
                    (
                        entityId,
                        placeName,
                        placeLat,
                        placeLng,
                        Chk_Dt
                    )
                    VALUES
                    (
                        :entityId,
                        :placeName,
                        :placeLat,
                        :placeLng,
                        :chkDate
                    )
                 ";

            $data = Array(
                ":entityId"  => $token["entityId"],
                ":placeName" => $args["place_name"],
                ":placeLat"  => $args["place_lat"],
                ":placeLng"  => $args["place_long"],
                ":chkDate"   => $now
            );
            // Check if the archive insert succeeded
            if(Database::getInstance()->insert($query, $data) == 0)
                return Array("error" => "500", "message" => "Internal server error when attempting to create the new Checkin. Please try again.");
        }
        else
        {
            return Array("error" => "500", "message" => "Internal server error when attempting to create the new Checkin. Please try again.");
        }

        // Update score of tagged users and notify them
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
                        "senderName" => $token["firstName"] . " " . $token["lastName"],
                        "receiver" => (int)$taggedUser,
                        "message" => substr($token["firstName"], 0, strrpos($token["firstName"], " ")) . " has tagged you in a checkin.",
                        "type" => 3,
                        "date" => $now,
                        "messageId" => NULL,
                        "messageType" => NULL
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
        // Notify friends that you were tagged here, only select friends who wish to be notified
        else
            $taggedUsers = "0";

        // Set up friend push variables
        $filter      = Array("tagged" => $taggedUsers);
        $friends     = User::getFriends($token["entityId"], $filter);
        $friendCount = count($friends);
        $success     = 0;

        // Send a push notification to each of these friends
        if($friendCount > 0) {
            foreach ($friends["payload"] as $friend) {
                // Notify the user, only if they prompted to recieve notifications
                $query = "SELECT entity_id FROM setting WHERE entity_id = :userId AND notif_tag = 1";
                $data = Array("userId" => $friend["entity_id"]);

                // This expression will result to std::object or false - this is why we perform a boolean check
                $recipient = Database::getInstance()->fetch($query, $data);
                if ($recipient) {

                    // Configure the push payload, we trim the name so that if it was Alexander John, it becomes Alexander.
                    $pushPayload = Array(
                        "senderId" => $token["entityId"],
                        "senderName" => $token["firstName"] . " " . $token["lastName"],
                        "receiver" => $recipient->entity_id,
                        "message" => substr($token["firstName"], 0, strrpos($token["firstName"], " ")) . " just checked in to " . $args["place_name"],
                        "type" => 3,
                        "date" => $now,
                        "messageId" => NULL,
                        "messageType" => NULL
                    );

                    $res = $server->sendNotification($pushPayload);
                    if ($res == 203)
                        $friendCount++;
                }

                if ($success > 0)
                    $res["error"] = 200;

                // Update the master payload
                $friendPushes = $success . "/" . $friendCount . " pushes were sent to friends who wish to receive notifications.";
            }
        }

        // Process the callback
        if(array_key_exists("error", $res))
        {
            if($res["error"] == 200 || $res["error"] == 400)
            {
                $people = self::getPeopleAtLocation($args["place_name"], $args["place_lat"], $args["place_long"], $args["entity_id"]);
                $res["message"] = "The Checkin was created successfully.";
                $res["payload"] = Array("details" => $placeData, "tagged_pushes" => $taggedPushes, "friend_pushes" => $friendPushes, "people" => $people);
                return $res;
            }
            else
                return Array("error" => "500", "message" => "Internal server error whilst creating this checkin.");
        }
        else
            return Array("error" => "500", "message" => "Internal server error whilst creating this checkin.");
    }

    /*
    |--------------------------------------------------------------------------
    | Get People At Location
    |--------------------------------------------------------------------------
    |
    | Returns a list of people who also checked in to a specific location.
    | This will retrieve every user at that specific lat/long and then wil l
    | provide enough information to be filtered on the front end.
    |
    | @param $place    - The name of the place that is to be returned
    | @param $lat      - The latitude of the place to be returned
    | @param $long     - The longitude of the place to be returned.
    | @param $entityId - The id of the user who just made a checkin
    |
    | @return $array   - Returns an array of users and their relation to the
    |                    current user.
    |
    */

    private function getPeopleAtLocation($place, $lat, $long, $entityId)
    {
        // Prepare for the LIKE clause
        $place = "%" . $place . "%";

        $query = "
            SELECT
                entity.entity_id,
                entity.first_name,
                entity.last_name,
                entity.profile_pic_url,
                TIMESTAMPDIFF(MINUTE, entity.last_checkin_dt, :now) AS ago,
                friends.category
            FROM entity
            JOIN checkins
              ON checkins.entity_id = entity.entity_id
            JOIN friends
              ON entity.entity_id = friends.entity_id2 OR entity.entity_id = friends.entity_id1
            WHERE entity_id IN
            (
                SELECT entity_id1 FROM friends WHERE entity_id2 = :entId
                UNION ALL
                SELECT entity_id2 FROM friends WHERE entity_id1 = :entId
            )
            AND checkins.Place_Name LIKE :place
            AND checkins.Place_Lat  =    :lat
            AND checkins.Place_Long =    :long


        ";

        $data = Array(
            ":place" => $place,
            ":lat"   => $lat,
            ":long"  => $long,
            ":entId" => $entityId
        );

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

    public static function deleteCheckin($args)
    {
        if(empty($args["checkinId"]) || empty($args["session_token"]) || empty($args["device_id"]))
            return Array("error" => "401", "message" => "Bad request, you are not authorised to delete this resource.");

        $user = Session::validateSession($args["session_token"], $args["device_id"]);

        // Check if the session was validated
        if($user["error"] != 200)
            return Array("error" => "401", "message" => "Bad request, you are not authorised to delete this resource.");
        else
        {
            // Perform the DELETE on the session
            $query = "DELETE FROM Checkins WHERE Chk_Id = :checkinId AND Entity_Id = :entityId";
            $data  = Array();

            $res   = Database::getInstance()->delete($query, $data);
            if($res > 0)
            {
                // Delete from the tags table
                $query = "DELETE FROM tags WHERE checkin_id = :checkinId";
                $data  = Array();

                $res = Database::getInstance()->delete($query, $data);
                if($res > 0)
                {
                    return Array("error" => "203", "message" => "The checkin and its associated tagged users were deleted successfully.");
                }
            }
            return Array("error" => "203", "message" => "The checkin was deleted successfully.");
        }

        return Array("error" => "500", "message" => "There was an error when deleting the resouce. Please ensure the object sent to the server is valid.");
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

    private static function uploadImage($image)
    {
        // Upload the checkin image if one was set
        if(!empty($image))
        {
            $allowedExts = Array("jpg", "png");
            $flag = 1;

            $_FILES['file'] = $_FILES["image"];
            $temp = explode(".", $_FILES["file"]["name"]);
            $extension = end($temp);
            $imageURL = './public/img/checkins/c' . (int)(((rand(21, 500) * rand(39, 9000)) / rand(3,9))) . time() * rand(2, 38) . "." . $extension;
            if(in_array($extension, $allowedExts))
            {
                if($_FILES['file']['error'] > 0)
                    $flag = 400;
                else
                    $flag = move_uploaded_file($_FILES['file']['tmp_name'], $imageURL);

                if ($flag == 1)
                    $flag = 201;
            }
        }

        // Final return type, should be 204, if 0 or array then the upload failed and we return an error back to the client.
        return Array("error" => $flag, "imageUrl" => $imageURL);
    }

    /*
    |--------------------------------------------------------------------------
    | GET USER MAPS
    |--------------------------------------------------------------------------
    |
    | TODO: ADD DESCRIPTION
    |
    */

    public static function getUserMaps($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
    |--------------------------------------------------------------------------
    | GET PROFILE MAPS
    |--------------------------------------------------------------------------
    |
    | TODO: ADD DESCRIPTION
    |
    */

    public static function getProfileMaps($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }


}