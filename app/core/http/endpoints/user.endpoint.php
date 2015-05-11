<?php

namespace Endpoints;

/*
* Ensure we include the reference to the handler for this object.
*/

require "./app/core/http/handlers/user.handler.php";

/*
|--------------------------------------------------------------------------
| User Endpoint
|--------------------------------------------------------------------------
|
| Defines the implementation of a User handler, this method will decide
| which method needs to be executed within the user handler class.
| The user handler class is responsible for managing DB transactions.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

class User
{
    /*
    |--------------------------------------------------------------------------
    | URI Templates
    |--------------------------------------------------------------------------
    |
    | Define URI templates which will be matched when handling the request
    |
    */

    private $allUsersTemplate;
    private $userFriendsTemplate;
    private $specificUserTemplate;
    private $usersAtLocationTemplate;
    private $userFavoritePlace;

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
    | Constructs a new user handler object, from here we can get the method,
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
            case "PUT"    : $this->_PUT();
                break;
            case "POST"   : return $this->_POST();
                break;
            case "DELETE" : $this->_DELETE();
                break;
            default       : throw new \Exception("Unsupported header type for resource: User");
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
        // /api/v2/User - Returns all users in the system
        if(count($this->args) == 0 && $this->verb == "")
        {
            $x = \Handlers\User::getAll();
            var_dump($x);
        }
        // /api/v2/User/{userId} - Returns the user
        else if(count($this->args) == 1 && $this->verb == "")
        {
            return \Handlers\User::get($this->args[0]);
        }
        // /api/v2/User/at-location/{lat}/{long}/{radius}/{limit} - Returns list of users at location
        else if(count($this->args) == 4 && $this->verb == "at-location")
        {
            return \Handlers\User::getUsersAtLocation($this->args[0], $this->args[1], $this->args[2], $this->args[3]);
        }
        // /api/v2/User/friends/{userId} - Returns the friends of the user
        else if(count($this->args) == 1 && $this->verb == "friends")
        {
            return \Handlers\User::getFriends($this->args[0]);
        }
        // /api/v2/User/friend-requests/{userId} - Returns the requests for the user
        else if(count($this->args) == 1 && $this->verb == "friend-requests")
        {
            return \Handlers\User::getFriendRequests($this->args[0]);
        }
        // /api/v2/User/favorite-place/{userId} - Returns users favorite places
        else if(count($this->args) == 1 && $this->verb == "favorite-places")
        {
            return \Handlers\User::getFavoritePlaces($this->args[0]);
        }
        // throw an exception if no handler found
        else
        {
            throw new \Exception("No handler found for the GET resource of this URI, please check the documentation.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUT
    |--------------------------------------------------------------------------
    |
    | Calls the correct GET method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _PUT()
    {
        // /api/v2/User/location/lat/long
        if(count($this->args) == 2 && $this->verb == "location")
        {

        }
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    |
    | Calls the correct GET method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _POST()
    {
        // Get the payload and decode it to a PHP array, set true to ensure assoc behaviour.
        $payload = json_decode(file_get_contents('php://input'), true);

        // Check for an invalid payload
        if ($payload == null)
            return Array("error" => "400", "message" => "Bad request, please ensure you have sent a valid User payload to the server.");

        // Perform template match and then handle the correct query.
        if(count($this->args) == 0 && $this->verb == "")
        {
            return \Handlers\User::login($payload);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    |
    | Calls the correct GET method relative to the matching URI template. The
    | transaction is handled in the handler class.
    |
    */

    private function _DELETE()
    {

    }
}