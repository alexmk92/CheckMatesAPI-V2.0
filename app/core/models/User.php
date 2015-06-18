<?php

namespace Models;

/*
|--------------------------------------------------------------------------
| User
|--------------------------------------------------------------------------
|
| Singleton user object to persist the logged in users details
|
*/

class User
{

    /*
    |--------------------------------------------------------------------------
    | Local vars
    |--------------------------------------------------------------------------
    |
    | Declare any local variable which the class should access
    | $instance   - the single instanciated instance of the class (singleton)
    | $connection - the connection to the database
    |
    */

    private static $instance;
    private        $user;

    /*
    |--------------------------------------------------------------------------
    | Construct
    |--------------------------------------------------------------------------
    |
    | Builds the database and sets the connection, applying the singleton
    | pattern to allow one open instaciation socket to the DB
    |
    */

    private function __construct($user)
    {
        $this->user = $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch
    |--------------------------------------------------------------------------
    |
    | Returns the logged in user
    |
    */

    private function fetch()
    {
        return $this->user;
    }
}