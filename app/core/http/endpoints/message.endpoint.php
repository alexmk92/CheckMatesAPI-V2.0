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

    private $user;

    /*
     |--------------------------------------------------------------------------
     | Constructor
     |--------------------------------------------------------------------------
     |
     | Constructs a new Checkin handler object, from here we can get the method,
     | verb, arguments and other URI information to perform the request with.
     |
     */

    function __construct(\API $sender)
    {
        // Extract info from payload into the encapsulated variables.
        $info = $sender->_getInfo();
        $this->verb   = $info['verb'];
        $this->args   = $info['args'];
        $this->method = $info['method'];
        $this->user   = \Models\User::getInstance()->fetch();
    }

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
            default       : throw new \Exception("Unsupported header type for resource: Message");
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
        $userId = $this->user["entityId"];

        // /api/v2/Message/messages/{friendId} - Get all of the messages between a friend and the user that made the request.
        if(count($this->args) == 1 && $this->verb == 'messages')
        {
            return \Handlers\Message::getChatMessages($this->args[0], $userId);
        }
        // /api/v2/Message/conversations/{userId} - Get all of the conversations that a user is currently a part of.
        else if(count($this->args) == 1 && $this->verb == 'conversations')
        {
            return \Handlers\Message::getConversations($this->args[0]);
        }

        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");

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
        // Retrieve the payload and send with the friendId to the handler.
        $payload = json_decode(file_get_contents('php://input'), true);

        // /api/v2/Message/send-message/{friendId} - Send a message to a friend.
        if(count($this->args) == 1 && $this->verb == 'send-message')
        {
            return \Handlers\Message::sendMessage($this->args[0], $payload, $this->user);
        }
        // /api/v2/Message/report-email/{$reportId} - Report a message, along with the user information of the person who sent it.
        else if(count($this->args) == 1 && $this->verb == 'report-email')
        {
            return \Handlers\Message::reportEmail($payload, $this->args[0], $this->user);
        }
        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");

    }

    /*
     |--------------------------------------------------------------------------
     | DELETE
     |--------------------------------------------------------------------------
     |
     | Calls the correct DELETE method relative to the matching URI template. The
     | transaction is handled in the handler class.
     |
     |
     */

    private function _DELETE()
    {
        // Retrieve the payload and send with the friendId to the handler.
        $payload = json_decode(file_get_contents('php://input'), true);

        // /api/v2/Message/delete-message/{messageId} - delete a message between two users.
        if(count($this->args) == 1 && $this->verb == 'delete-message')
        {
            return \Handlers\Message::deleteMessage($this->args[0], $this->user);
        }
        // /api/v2/Message/delete-conversation/{friendId} - delete a message between two users.
        else if(count($this->args) == 1 && $this->verb == 'delete-conversation')
        {
            return \Handlers\Message::deleteConversation($payload, $this->args[0], $this->user);
        }
        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");

    }

}