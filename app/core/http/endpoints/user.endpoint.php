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
     * Property: File
     * The PUT info
     */

    private $file;

    private $user;

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
        $this->file   = $info['file'];
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
            return \Handlers\User::getAll();
        }
        // /api/v2/User/{userId} - Returns the user
        else if(count($this->args) == 1 && $this->verb == "")
        {
            return \Handlers\User::get($this->args[0], $this->user);
        }
        // /api/v2/User/at-location/{long}/{lat}/{limit*} - Returns list of users at location
        else if((count($this->args) == 3 || count($this->args) == 2) && $this->verb == "at-location")
        {
            if(count($this->args) == 2)
                $this->args[2] = 200;
            return \Handlers\User::getUsersAtLocation($this->args[0], $this->args[1], $this->args[2], $this->user);
        }
        // /api/v2/User/favorite-place/{userId} - Returns users favorite places
        else if(count($this->args) == 1 && $this->verb == "favorite-places")
        {
            return \Handlers\User::getFavoritePlaces($this->args[0]);
        }
        // /api/v2/User/preferences/{userId} - Get the preferences for a user.
        else if(count($this->args) == 1 && $this->verb == 'preferences')
        {
            return \Handlers\User::getPreferences($this->args[0]);
        }
        // /api/v2/User/settings/{userId} - Get the settings of the user.
        else if(count($this->args) == 1 && $this->verb == 'settings')
        {
            return \Handlers\User::getSettings($this->args[0]);
        }
        // /api/v2/User/list/{userId}
        else if(count($this->args) == 1 && $this->verb == 'list')
        {
            return \Handlers\User::getLists($this->args[0]);
        }
        // /api/v2/User/score/{userId}
        else if(count($this->args) == 1 && $this->verb == 'score')
        {
            return \Handlers\User::getScores($this->args[0]);
        }
        // /api/v2/User/profile/{userId}
        else if(count($this->args) == 1 && $this->verb == 'profile')
        {
            return \Handlers\User::getProfile($this->args[0]);
        }
        // /api/v2/User/notifications/{userId}
        else if(count($this->args) == 1 && $this->verb == 'notifications')
        {
            return \Handlers\User::getNotifications($this->args[0]);
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
        // Get the payload and decode it to a PHP array, set true to ensure assoc behaviour.
        $payload = json_decode($this->file, true);

        // Check for an invalid payload
        if ($payload == null)
            return Array("error" => "400", "message" => "Bad request, please ensure you have sent a valid User payload to the server.");

        // /api/v2/User/location/lat/long
        if(count($this->args) == 2 && $this->verb == "update-location")
        {
            if(empty($payload["lat"]))
                $payload["lat"] = $this->args[0];
            if(empty($payload["long"]))
                $payload["long"] = $this->args[1];
            return \Handlers\User::updateLocation($payload, $this->user);
        }

        // /api/v2/User/update-settings/{userId} - Update the settings for the user with one or more values provided.
        if(count($this->args) == 1 && $this->verb == 'update-settings')
        {
            return \Handlers\User::updateSettings($payload, $this->args[0], $this->user);
        }
        // /api/v2/User/update-score/{scoreValue} - Update a users score - add to it.
        if(count($this->args) == 1 && $this->verb == 'update-score')
        {
            return \Handlers\User::updateScore($payload, $this->args[0], $this->user);
        }

        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/User/update-favourite/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(UPDATEFAVOURITE)
        if(count($this->args) == 1 && $this->verb == 'update-favourite')
        {
            return \Handlers\User::updateFavourite($this->args[0]);
        }
        // /api/v2/User/update-preference/{userId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(UPDATEPREFERENCES)
        if(count($this->args) == 1 && $this->verb == 'update-preferences')
        {
            return \Handlers\User::updatePreferences($payload, $this->args[0], $this->user);
        }
        // throw an exception if no handler found
        else
        {
            throw new \Exception("No handler found for the PUT resource of this URI, please check the documentation.");
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
        // Get the payload and decode it to a PHP array, set true to ensure assoc behaviour.
        $payload = json_decode(file_get_contents('php://input'), true);

        // Check for an invalid payload
        if ($payload == null)
            return Array("error" => "400", "message" => "Bad request, please ensure you have sent a valid User payload to the server.");

        // /api/v2/User/ Performs a loginlogin function, if the user does not exist they will be registered.
        if(count($this->args) == 0 && $this->verb == "login")
            return \Handlers\User::login($payload);

        // /api/v2/User/add-favourite/{userId} - Add a new favourite place.
        if(count($this->args) == 1 && $this->verb == 'add-favourite')
        {
            return \Handlers\User::addFavourite($payload, $this->args[0], $this->user);
        }
        // throw an exception if no handler found
        else
        {
            throw new \Exception("No handler found for the POST resource of this URI, please check the documentation.");
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
        // Get the payload and decode it to a PHP array, set true to ensure assoc behaviour.
        $payload = json_decode(file_get_contents('php://input'), true);

        // /api/v2/User/remove-user/{userId} - Delete the users account.
        if(count($this->args) == 1 && $this->verb == 'remove-user')
        {
            return \Handlers\User::deleteAccount($payload, $this->args[0], $this->user);
        }
        // /api/v2/User/remove-favourite/{likeId} - Remove a favourite place.
        if(count($this->args) == 1 && $this->verb == 'remove-favourite')
        {
            return \Handlers\User::removeFavourite($this->args[0], $this->user);
        }
        // throw an exception if no handler found
        else
        {
            throw new \Exception("No handler found for the DELETE resource of this URI, please check the documentation.");
        }
    }
}