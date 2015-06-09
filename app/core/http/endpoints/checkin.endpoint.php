<?php

namespace Endpoints;

/*
* Ensure we include the reference to the handler for this object.
*/

require "./app/core/http/handlers/checkin.handler.php";

/*
|--------------------------------------------------------------------------
| Checkin Endpoint
|--------------------------------------------------------------------------
|
| Defines the implementation of a Checkin handler, this method will decide
| which method needs to be executed within the Checkin handler class.
| The Checkin handler class is responsible for managing DB transactions.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

class Checkin
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
        // /api/v2/Checkin - Returns all Checkins in the system
        if(count($this->args) == 0 && $this->verb == "")
        {
            return \Handlers\Checkin::getAll();
        }
        // /api/v2/Checkin/{CheckinId} - Returns the Checkin
        else if(count($this->args) == 1 && $this->verb == "")
        {
            return \Handlers\Checkin::get($this->args[0]);
        }
        // /api/v2/Checkin/at-location/{lat}/{long}/{radius}/{limit} - Returns list of Checkins at location
        else if(count($this->args) == 4 && $this->verb == "at-location")
        {
            return \Handlers\Checkin::getCheckinsAtLocation($this->args[0], $this->args[1], $this->args[2], $this->args[3]);
        }
        // /api/v2/Checkin/users-at/{CheckinId} - Returns the list of users at the checkin
        else if(count($this->args) == 1 && $this->verb == 'users-at')
        {
            return \Handlers\Checkin::getUsersAtCheckin($this->args[0]);
        }

        //*************************************************************************************//
        //                        TODO: SECTION FOR UNIMPLEMENTED
        //*************************************************************************************//

        // /api/v2/Checkin/user-maps/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETUSERMAPS)
        else if(count($this->args) == 1 && $this->verb == 'users-maps')
        {
            return \Handlers\Checkin::getUserMaps($this->args[0]);
        }
        // /api/v2/Checkin/profile-maps/{CheckinId} - TODO: REVISE ARGUMENTS + ADD DESCRIPTION(GETPROFILEMAPS)
        else if(count($this->args) == 1 && $this->verb == 'profile-maps')
        {
            return \Handlers\Checkin::getProfileMaps($this->args[0]);
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
    | Calls the correct PUT method relative to the matching URI template.  The
    | update transaction is then handled in the handler class.
    |
    */

    private function _PUT()
    {

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

        // Check for an invalid payload - we check FILES and POST arrays here for multipart data.
        if (empty($payload) && (empty($_FILES) && empty($_POST)))
            return Array("error" => "400", "message" => "Bad request, please ensure you have sent a valid Checkin payload to the server.");
        // Account for a payload that has been sent as a multipart form-data
        if (empty($payload))
            $payload = Array("image" => $_FILES, "args" => $_POST);

        // /api/v2/Checkin/ Posts the new checkin that the user has sent in the multipart body - this will only post checkin and not the image
        if(count($this->args) == 0 && $this->verb == "")
            return \Handlers\Checkin::createCheckin($payload);

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