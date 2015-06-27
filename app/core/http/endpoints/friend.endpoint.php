<?php

namespace Endpoints;

/*
* Ensure we include the reference to the handler for this object.
*/

use Handlers;
require "./app/core/http/handlers/friend.handler.php";

/*
|--------------------------------------------------------------------------
| Friend Endpoint
|--------------------------------------------------------------------------
|
| Defines the implementation of a Friend handler, this method will decide
| which method needs to be executed within the user handler class.
| The user handler class is responsible for managing DB transactions.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

class Friend
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
            case "PUT"    : return $this->_PUT();
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
        // /api/v2/Friend/{userId} - Returns the friends of the user
        if(count($this->args) == 1 && $this->verb == "")
            return \Handlers\Friend::getFriends($this->args[0]);
        // /api/v2/Friend/friend-requests/{userId} - Returns the requests for the user
        else if(count($this->args) == 1 && $this->verb == "friend-requests")
            return \Handlers\Friend::getFriendRequests($this->args[0]);
        // /api/v2/Friend/suggested-friends/{userId}
        else if(count($this->args) == 1 && $this->verb == "suggested-friends")
            return \Handlers\Friend::getSuggestedFriends($this->args[0]);

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

        // /api/v2/Friend/send-request/{friendId} - Sends a friend request.
        if(count($this->args) == 1 && $this->verb == 'send-request')
        {
            return \Handlers\Friend::sendFriendRequest($this->args[0], $this->user);
        }
        // /api/v2/Friend/accept-request/{friendId} - Accepts a friend request.
        else if(count($this->args) == 1 && $this->verb == 'accept-request')
        {
            return \Handlers\Friend::acceptFriendRequest($this->args[0], $payload, $this->user);
        }
        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");
    }

    /*
    |--------------------------------------------------------------------------
    | PUT
    |--------------------------------------------------------------------------
    |
    | Calls the correct PUT method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _PUT()
    {
        // /api/v2/Friend/block/{friendId} - Blocks communication between two users. Initiated by the entityId.
        if(count($this->args) == 1 && $this->verb == 'block')
        {
            $userId = $this->user["entityId"];

            if(empty($userId))
                return array("error" => "422", "message" => "Unprocessable entity: The underpinning logic of the
                            operation cannot be performed. Please make sure the required parameters are included in
                            the headers for a PUT HTTP request. ", "payload" => "");

            return \Handlers\Friend::blockUser($userId, $this->args[0]);
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
    */

    private function _DELETE()
    {
        // Retrieve the payload and send with the friendId to the handler.
        $payload = json_decode(file_get_contents('php://input'), true);

        // Check for an invalid payload
        if ($payload == null)
            return Array("error" => "400", "message" => "Bad request, please ensure you have sent a valid User payload to the server.");

        // /api/v2/Friend/remove-friend/{friendId} - Deletes a friend taking into account different categories.
        if(count($this->args) == 1 && $this->verb == 'remove-friend')
        {
            return \Handlers\Friend::removeFriend($this->args[0], $payload, $this->user);
        }
        // /api/v2/Friend/reject-request/{friendId} - Deletes a request between a user and the potential friend.
        else if(count($this->args) == 1 && $this->verb == 'reject-request')
        {
            return \Handlers\Friend::rejectFriendRequest($this->args[0], $payload, $this->user);
        }
        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");
    }

}
