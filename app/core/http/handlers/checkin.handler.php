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
    | Checkin
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

    public static function checkin($args)
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
        $token = Session::validateSession($args["sess_token"], $args["dev_id"]);

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
                $res["message"] = "The Checkin was created successfully.";
                $res["payload"] = Array("details" => $placeData, "tagged_pushes" => $taggedPushes, "friend_pushes" => $friendPushes);
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

}