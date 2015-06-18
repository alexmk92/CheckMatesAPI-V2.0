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

    private function __construct()
    {
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Get instance
    |--------------------------------------------------------------------------
    |
    | Returns the set instance to ensure only one socket stays open.
    |
    | @return Database - The instance of this class.
    |
    */

    public static function getInstance($debug = false)
    {
        $object = __CLASS__;
        !isset(self::$instance) ? self::$instance = new $object($debug) : false;
        return self::$instance;
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch
    |--------------------------------------------------------------------------
    |
    | Returns the logged in user
    |
    */

    public function fetch()
    {
        return $this->user;
    }
}