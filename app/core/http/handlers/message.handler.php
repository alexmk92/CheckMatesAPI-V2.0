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
     | TODO: ADD DESCRIPTION
     |
     */

    public static function getComments($args)
    {
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