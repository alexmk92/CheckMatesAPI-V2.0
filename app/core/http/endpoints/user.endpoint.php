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

    function _handleRequest()
    {
        $method = $this->info['method'];
        var_dump($this->info);
        echo $method;
    }
}