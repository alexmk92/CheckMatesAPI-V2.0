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
        if(count($this->args) == 1)
            return \Handlers\Friend::getFriends($this->args[0]);
        // /api/v2/Friend/friend-requests/{userId} - Returns the requests for the user
        else if(count($this->args) == 1 && $this->verb == "friend-requests")
            return \Handlers\Friend::getFriendRequests($this->args[0]);
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
        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/Friend/send-request/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(SENDFRIENDREQUEST)
        if(count($this->args) == 1 && $this->verb == 'send-request')
        {
            return Friend::sendFriendRequest($this->args[0]);
        }
        // /api/v2/Friend/accept-request/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(ACCEPTFRIENDREQUEST)
        else if(count($this->args) == 1 && $this->verb == 'accept-request')
        {
            return Friend::acceptFriendRequest($this->args[0]);
        }
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
        // /api/v2/Friend/block/{EntityId} - TODO: Write impelementation to block this user
        if(count($this->args) == 1 && $this->verb == 'block')
        {
            return Friend::blockFriend();
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
