<?php
/**
 * Created by PhpStorm.
 * User: Adamst
 * Date: 05/06/2015
 * Time: 19:14
 */

namespace Endpoints;

/*
* Ensure we include the reference to the handler for this object.
*/

require "./app/core/http/handlers/message.handler.php";

/*
|--------------------------------------------------------------------------
| Message Endpoint
|--------------------------------------------------------------------------
|
| Defines the implementation of a Message handler, this method will decide
| which method needs to be executed within the user handler class.
| The user handler class is responsible for managing DB transactions.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

class Message
{

    /*
     * Property: Verb
     * The verb for the resource
     */

    private $verb;

    /*
    * Property: Args
    * Any extra arguments to filter the request
    */

    private $args;

    /*
    * Property: Args
    * The method for this request
    */

    private $method;

    /*
    |--------------------------------------------------------------------------
    | Handle Request
    |--------------------------------------------------------------------------
    |
    | Handles the request by deciding which resource needs to be processed.
    |
    */

    function _handleRequest()
    {
        // Delegate to the correct template matching method
        switch($this->method)
        {
            case "GET"    : return $this->_GET();
                break;
            case "POST"   : return $this->_POST();
                break;
            case "DELETE" : return $this->_DELETE();
                break;
            default       : throw new \Exception("Unsupported header type for resource: Checkin");
                break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    |
    | Calls the correct GET method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _GET()
    {
        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/Message/comments/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETCOMMENTS)
        if(count($this->args) == 1 && $this->verb == 'comments')
        {
            return \Handlers\Message::getComments($this->args[0]);
        }
        // /api/v2/Message/history/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETCHATHISTORY)
        if(count($this->args) == 1 && $this->verb == 'history')
        {
            return \Handlers\Message::getChatHistory($this->args[0]);
        }
        // /api/v2/Message/messages/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETCHATMESSAGES)
        if(count($this->args) == 1 && $this->verb == 'messages')
        {
            return \Handlers\Message::getChatMessages($this->args[0]);
        }


    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    |
    | Calls the correct POST method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _POST()
    {
        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/Message/like/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(LIKECOMMENT)
        if(count($this->args) == 1 && $this->verb == 'like')
        {
            return \Handlers\Message::likeMessage($this->args[0]);
        }
        // /api/v2/Message/add-comment/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(ADDCOMMENT)
        if(count($this->args) == 1 && $this->verb == 'add-comment')
        {
            return \Handlers\Message::addComment($this->args[0]);
        }
        // /api/v2/Message/send-message/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(SENDMESSAGE)
        if(count($this->args) == 1 && $this->verb == 'send-message')
        {
            return \Handlers\Message::sendMessage($this->args[0]);
        }
        // /api/v2/Message/report-email/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(REPORTEMAIL)
        if(count($this->args) == 1 && $this->verb == 'report-email')
        {
            return \Handlers\Message::reportEmail($this->args[0]);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | DELETE
     |--------------------------------------------------------------------------
     |
     | Calls the correct DELETE method relative to the matching URI template. The
     | transaction is handled in the handler class.
     |
     */

    private function _DELETE()
    {

    }

}