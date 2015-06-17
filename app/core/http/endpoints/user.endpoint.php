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
            return \Handlers\User::get($this->args[0]);
        }
        // /api/v2/User/at-location/{lat}/{long}/{radius}/{limit} - Returns list of users at location
        else if(count($this->args) == 4 && $this->verb == "at-location")
        {
            return \Handlers\User::getUsersAtLocation($this->args[0], $this->args[1], $this->args[2], $this->args[3]);
        }
        // /api/v2/User/favorite-place/{userId} - Returns users favorite places
        else if(count($this->args) == 1 && $this->verb == "favorite-places")
        {
            return \Handlers\User::getFavoritePlaces($this->args[0]);
        }


        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/User/list/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETLISTS)
        else if(count($this->args) == 1 && $this->verb == 'list')
        {
            return \Handlers\User::getLists($this->args[0]);
        }
        // /api/v2/User/score/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETSCORES)
        else if(count($this->args) == 1 && $this->verb == 'score')
        {
            return \Handlers\User::getScores($this->args[0]);
        }
        // /api/v2/User/profile/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETPROFILE)
        else if(count($this->args) == 1 && $this->verb == 'profile')
        {
            return \Handlers\User::getProfile($this->args[0]);
        }
        // /api/v2/User/preferences/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETPROFILE)
        else if(count($this->args) == 1 && $this->verb == 'preferences')
        {
            return \Handlers\User::getPreferences($this->args[0]);
        }
        // /api/v2/User/recent-user/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETRECENTUSER)
        else if(count($this->args) == 1 && $this->verb == 'recent-user')
        {
            return \Handlers\User::getRecentUser($this->args[0]);
        }
        // /api/v2/User/settings/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETSETTINGS)
        else if(count($this->args) == 1 && $this->verb == 'settings')
        {
            return \Handlers\User::getSettings($this->args[0]);
        }
        // /api/v2/User/notifications/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETNOTIFICATIONS)
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
            return \Handlers\User::updateLocation($payload);
        }
        // /api/v2/User/friends/requests/respond
        if(count($this->args) == 2 && $this->verb == "friends")
            return \Handlers\User::respondToFriendRequest($payload);



        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/User/update-favourite/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(UPDATEFAVOURITE)
        if(count($this->args) == 1 && $this->verb == 'update-favourite')
        {
            return \Handlers\User::updateFavourite($this->args[0]);
        }
        // /api/v2/User/update-preference/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(UPDATEPREFERENCES)
        if(count($this->args) == 1 && $this->verb == 'update-preference')
        {
            return \Handlers\User::updatePreferences($this->args[0]);
        }
        // /api/v2/User/update-settings/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(UPDATESETTINGS)
        if(count($this->args) == 1 && $this->verb == 'update-settings')
        {
            return \Handlers\User::updateSettings($this->args[0]);
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

        // /api/v2/User/ Performs a login function, if the user does not exist they will be registered.
        if(count($this->args) == 0 && $this->verb == "")
            return \Handlers\User::login($payload);

        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/User/block/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(BLOCKUSER)
        if(count($this->args) == 1 && $this->verb == 'block')
        {
            return \Handlers\User::blockUser($this->args[0]);
        }
        // /api/v2/User/add-favourite/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(ADDFAVOURITE)
        if(count($this->args) == 1 && $this->verb == 'add-favourite')
        {
            return \Handlers\User::addFavourite($this->args[0]);
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
        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/User/unfriend/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(REMOVEFRIEND)
        if(count($this->args) == 1 && $this->verb == 'unfriend')
        {
            return \Handlers\User::removeFriend($this->args[0]);
        }
        // /api/v2/User/remove-most-recent/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(DELETEMOSTRECENT)
        if(count($this->args) == 1 && $this->verb == 'remove-most-recent')
        {
            return \Handlers\User::removeMostRecent($this->args[0]);
        }
        // /api/v2/User/remove-user/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(REMOVEUSER)
        if(count($this->args) == 1 && $this->verb == 'remove-user')
        {
            return \Handlers\User::removeUser($this->args[0]);
        }
        // /api/v2/User/remove-favourite-place/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(REMOVEFAVOURITEPLACE)
        if(count($this->args) == 1 && $this->verb == 'remove-favourite-place')
        {
            return \Handlers\User::removeFavourite($this->args[0]);
        }
    }
}