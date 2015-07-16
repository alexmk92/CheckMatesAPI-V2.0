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
        // /api/v2/Checkin/around-location/long/lat
        if(count($this->args) == 2 && $this->verb == "around-location")
        {
            $headers = apache_request_headers();
            if(!empty($headers))
            {
                $args = Array(
                    "curr_long" => $this->args[0],
                    "curr_lat" => $this->args[1]
                );
            }
            return \Handlers\Checkin::getCheckins($args, $this->user);
        }
        // /api/v2/Checkin/activities/{userId}
        else if(count($this->args) == 1 && $this->verb == "activities")
        {
            return \Handlers\Checkin::getActivities($this->args[0]);
        }
        // /api/v2/Checkin/for-user/{userId}
        else if(count($this->args) == 1 && $this->verb == "for-user")
        {
            $headers = apache_request_headers();
            if(!empty($headers))
            {
                $args = Array(
                    "session_token" => $headers["session_token"],
                    "device_id" => $headers["device_id"],
                    "entityId" => $this->args[0]
                );
            }
            return \Handlers\Checkin::getUserCheckins($args, $this->user);
        }
        // /api/v2/Checkin/{CheckinId} - Returns the Checkin
        else if(count($this->args) == 1 && $this->verb == "")
        {
            $headers = apache_request_headers();
            if(!empty($headers))
            {
                $args = Array(
                    "session_token" => $headers["session_token"],
                    "device_id" => $headers["device_id"],
                    "checkinId" => $this->args[0]
                );
            }
            return \Handlers\Checkin::get($args, $this->user);
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
        // api/v2/Checkin/like-checkin/{checkinId}
        if(count($this->args) == 1 && $this->verb == "like-checkin")
            return \Handlers\Checkin::likeCheckin($this->args[0], $this->user["entityId"]);
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
            return \Handlers\Checkin::createCheckin($payload, $this->user);
        // /api/v2/Checkin/add-comment/{checkinId}
        if(count($this->args) == 1 && $this->verb == "add-comment")
            return \Handlers\Checkin::addComment($this->args[0], $payload, $this->user);

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
        // Delete the checkin /api/v2/Checkin/delete-checkin/{checkinId}
        if($this->verb == "delete-checkin" && count($this->args) == 1)
            return \Handlers\Checkin::deleteCheckin($this->args[0], $this->user["entityId"]);
    }
}