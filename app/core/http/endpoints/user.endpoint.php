<?php

namespace Endpoints;

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

    private $allUsersTemplate        = "/User/{limit}";
    private $specificUserTemplate    = "/User/{userId}";
    private $usersAtLocationTemplate = "/User/{lat}/{long}/{radius}/{limit}";

    /*
     * Property: Info
     * All info sent in the URI, once here we know we are authenticated
     */

    private $info;

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
        $this->info = $sender->_getInfo();
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
        switch($this->info['method'])
        {
            case "GET"    : $this->_GET();
                break;
            case "PUT"    : $this->_PUT();
                break;
            case "POST"   : $this->_POST();
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