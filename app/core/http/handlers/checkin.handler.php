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

// Include the session handler object
require "./app/core/http/handlers/session.handler.php";

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
        // Score modifier variables
        $scoreMod = 0;

        // Other local variables
        $placePic        = FALSE;
        $checkinPic      = FALSE;

        $file_to_open    = "";

        // Set default images for places
        $placeImageURL   = SERVER_IP . "/public/img/4bf58dd8d48988d124941735.png";

        // Ensure a valid session token has been provided
        $token = Session::validateSession($args["ent_sess_token"], $args["ent_dev_id"]);

        // If there was an error, then return the bad response object
        if(array_key_exists("error", $token))
            return $token;

        // Check all required fields are set
        if(empty($args["ent_place_name"]) || empty($args["ent_place_lat"]) || empty($args["ent_place_long"]) || empty($args["ent_place_country"]) || empty($args["ent_place_people"]))
            return Array("error" => "400", "message" => "The checkin provided must have its [ent_place_name], [ent_place_long], [ent_place_lat], [ent_place_country] and [ent_place_people] set, check the data you sent in the payload.", "payload" => $args);

        // Set the place badge image.
        if(!empty($args["ent_cat_id"]))
        {
            $file_to_open = './public/img/placeBadges/' . $args["ent_cat_id"] . ".png";
            if(file_exists($file_to_open)) {
                $placePic = TRUE;
            }
            else {
                $placePic = file_put_contents($file_to_open, file_get_contents($args["ent_place_url"]));
                $placeImageURL = SERVER_IP . "/" . $file_to_open;
            }
        }


        // +10 to score for each tagged user
    }

    /*
    |--------------------------------------------------------------------------
    | Checkin
    |--------------------------------------------------------------------------
    |
    | Sets the image for the checkin, this has to be done here as good REST
    | principles dictate we shouldn't send large data in our bodies. Therefore
    | we split the checkin process into two stages a) we insert our new checkin
    | and recieve a callback.  b) we set the image for the resource with a
    | multipart data request.
    |
    | @param $checkinId - The id of the checkin that we are setting an image
    | @param $sessToken - The session tokent o validate the user
    | @param $deviceId  - The id for the current device the user has.
    |
    | @return $arr - An array dictacting the response, whether this succeeded
    |                or failed.
    |
    */

    public static function setCheckinImage($checkinID, $sessionToken, $deviceId)
    {
        // Get the validated user so we can
        $user = Session::validateSession($sessionToken, $deviceId);

        // Upload the checkin image if one was set
        if(!empty($_FILES["ent_image"]))
        {
            $allowedExts = Array("jpg", "png");
            $flag = 1;
            $chkimg = "";
            $recTagArr = Array();
            $recFriendArray = Array();

            $_FILES['file'] = $_FILES["ent_image"];
            $temp = explode(".", $_FILES["file"]["name"]);
            $extension = end($temp);
            $imageURL = './public/img/checkins/c' . (int)(((rand(21, 500) * rand(39, 9000)) / rand(3,9))) . time() * rand(2, 38) . $extension;
            $chkimg = SERVER_IP . $imageURL;
            if(in_array($extension, $allowedExts))
            {
                if($_FILES['file']['error'] > 0)
                    $flag = 0;
                else
                    $flag = move_uploaded_file($_FILES['file']['tmp_name'], $imageURL);
            }

        }
    }

}