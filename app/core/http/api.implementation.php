<?php

/*
|--------------------------------------------------------------------------
| Checkmates API V2.0
|--------------------------------------------------------------------------
|
| Defines the implementation of the API, this will delegate calls to
| the specific endpoint resource based on the inbound request and
| request type (GET, PUT, POST or DELETE) - it will also handle
| responses and shall produce HATEOS formatted objects.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

require 'api.abstract.php';
require './app/core/models/User.php';

/*
|--------------------------------------------------------------------------
| Implementation
|--------------------------------------------------------------------------
|
| Create the API class which will handle all requests for the application,
| this is the entry point of any request and will delegate calls to
| other resources in the system. Hooray for clean code!
|
*/

class CheckmatesAPI extends API
{
    /*
    * Property: User
    * The user object will be set via the user Model class, this will validate
    * transactions to the API by ensuring that a valid API Key and Session
    * token have been provided by the authenticating party.
    */

    protected $User;

    /*
    |--------------------------------------------------------------------------
    | Method: Constructor
    |--------------------------------------------------------------------------
    |
    | Constructs a new implementation of the CheckmatesAPI, this will allow us
    | to begin interacting with our resources based on the inbound request.
    |
    | @param $request : The request header to be build
    | @param $origin  : Where was the request sent from
    |
    */

    public function __construct($request, $origin)
    {
        /*
        * Call the constructor in the abstract class to build the request header
        * and determine which endpoint we need to access
        */

        parent::__construct($request);

        /*
        * Authorise the session here, if the session isn't set then we can return
         * an error 401 back to the client.
         */

        /*
        if(empty($args["session_token"]) || empty($args["device_id"]))
            return Array("error" => "401", "message" => "Unauthorised: No session token was provided in the payload.");

        // Validate the session and ensure that the session was set, if not return a 401
        $user = Session::validateSession($args["session_token"], $args["device_id"]);
        if($user["error"] != 200)
            return Array("error" => "401", "message" => "You are not authorised to access this resource, please re-login to generate a new session token.");
        */

    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint: User
    |--------------------------------------------------------------------------
    |
    | Handle the request in a new User endpoint.
    |
    */

    protected function User() {
        require "./app/core/http/endpoints/user.endpoint.php";
        $userHandler = new Endpoints\User($this);
        return $userHandler->_handleRequest();
    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint: Message
    |--------------------------------------------------------------------------
    |
    | Handle the request in a new Message endpoint.
    |
    */

    protected function Message() {
        require "./app/core/http/endpoints/message.endpoint.php";
        $messageHandler = new Endpoints\Message($this);
        return $messageHandler->_handleRequest();
    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint: Friend
    |--------------------------------------------------------------------------
    |
    | Handle the request in a new Friend endpoint.
    |
    */

    protected function Friend() {
        require "./app/core/http/endpoints/friend.endpoint.php";
        $friendsHandler = new Endpoints\Friend($this);
        return $friendsHandler->_handleRequest();
    }

    /*
    |--------------------------------------------------------------------------
    | Endpoint: Checkin
    |--------------------------------------------------------------------------
    |
    | Handle the request in a new Checkin endpoint.
    |
    */

    protected function Checkin() {
        require "./app/core/http/endpoints/checkin.endpoint.php";
        $checkinHandler = new Endpoints\Checkin($this);
        return $checkinHandler->_handleRequest();
    }

}