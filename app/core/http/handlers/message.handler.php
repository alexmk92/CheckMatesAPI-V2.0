<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Message Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Message handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler object
require_once "./app/core/http/handlers/session.handler.php";

class Message
{

    /*
     |--------------------------------------------------------------------------
     | GET COMMENTS
     |--------------------------------------------------------------------------
     |
     | Get all of the comments for a checkin.
     |
     | @param $checkInId - The identifer for the checkIn.
     |
     | @param $payload   - All of the information about the user who made the request,
     |                     encoded in a JSON packet.
     |
     | @return             A list of comments about a checkIn.
     */

    public static function getComments($checkInId, $userId)
    {

        // Get all of the comments
        // Check to see if the user who wrote the comment is not blocked by the user who sent this request ($user->Entity_Id).
        $query = "SELECT ent.Entity_Id, ent.Profile_Pic_Url, ent.First_Name, ent.Last_Name, ent.Last_CheckIn_Place
                          FROM  checkin_likes likes
                          JOIN  entity ent
                          ON    likes.Entity_Id = ent.Entity_Id
                          WHERE Chk_Id = :checkInId


                          ";

        // Bind the parameters to the query
        $data = Array(":checkInId" => $checkInId);

        $results = Database::getInstance()->fetchAll($query, $data);

        if(!empty($results))
        {
            var_dump($results);
        }

        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET CHAT MESSAGES
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getChatMessages($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET CHAT HISTORY
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getChatHistory($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) LIKE MESSAGE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function likeMessage($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ADD COMMENT
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function addComment($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) SEND MESSAGE
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function sendMessage($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) REPORT EMAIL
     |--------------------------------------------------------------------------
     |
     | TODO: ADD DESCRIPTION
     |
     */

    public static function reportEmail($args)
    {
        return Array("error" => "501", "message" => "Not currently implemented.");
    }
}