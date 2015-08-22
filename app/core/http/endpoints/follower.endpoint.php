<?php

namespace Endpoints;

/*
* Ensure we include the reference to the handler for this object.
*/

use Handlers;
require "./app/core/http/handlers/follower.handler.php";

/*
|--------------------------------------------------------------------------
| Follower Endpoint
|--------------------------------------------------------------------------
|
| Defines the implementation of a Follower handler, this method will decide
| which method needs to be executed within the user handler class.
| The user handler class is responsible for managing DB transactions.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

class Follower
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
            default       : throw new \Exception("Unsupported header type for resource: Check-In");
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
        // /api/v2/Follower/{userId} - Returns the Followers of the user
        if(count($this->args) == 1 && $this->verb == "")
            return \Handlers\Follower::getFollowers($this->args[0]);
        // /api/v2/Follower/Follower-requests/{userId} - Returns the requests for the user
        else if(count($this->args) == 1 && $this->verb == "Follower-requests")
            return \Handlers\Follower::getFollowerRequests($this->args[0], $this->user);
        // /api/v2/Follower/suggested-followers/{userId}
        else if(count($this->args) == 1 && $this->verb == "suggested-followers")
            return \Handlers\Follower::getSuggestedFollowers($this->args[0], $this->user);
        // /api/v2/Follower/blocked-users/{userId}
        else if(count($this->args) == 1 && $this->verb == "blocked-users")
            return \Handlers\Follower::getBlockedUsers($this->args[0], $this->user);

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
        // Retrieve the payload and send with the FollowerId to the handler.
        $payload = json_decode(file_get_contents('php://input'), true);

        // /api/v2/Follower/follow/{FollowerId} - Follows a user
        if(count($this->args) == 1 && $this->verb == 'follow')
        {
            return \Handlers\Follower::followUser($this->args[0], $payload, $this->user);
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
        // /api/v2/Follower/block/{FollowerId} - Blocks communication between two users. Initiated by the entityId.
        if(count($this->args) == 1 && $this->verb == 'block')
            return \Handlers\Follower::blockUser($this->args[0], $this->user["entityId"]);
        else if(count($this->args) == 1 && $this->verb == 'unblock')
            return \Handlers\Follower::unblockUser($this->args[0], $this->user["entityId"]);
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
        // Retrieve the payload and send with the FollowerId to the handler.
        $payload = json_decode(file_get_contents('php://input'), true);

        // /api/v2/Follower/remove-Follower/{FollowerId} - Deletes a Follower taking into account different categories.
        if(count($this->args) == 1 && $this->verb == 'unfollow')
        {
            return \Handlers\Follower::unfollow($this->args[0], $payload, $this->user);
        }
        // /api/v2/Follower/reject-request/{FollowerId} - Deletes a request between a user and the potential Follower.
        else if(count($this->args) == 1 && $this->verb == 'reject-request')
        {
            return \Handlers\Follower::rejectFollowerRequest($this->args[0], $this->user);
        }
        // Unsupported handler
        else
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");
    }

}
