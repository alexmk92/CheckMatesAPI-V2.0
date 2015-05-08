<?php

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
|
| The database class maintains a single connection to the server and
| utilises PDO for safe parameter binding.  This is required to
| stop malicious users for exposing application logic.
|
*/

class Database
{

    /*
    |--------------------------------------------------------------------------
    | Local vars
    |--------------------------------------------------------------------------
    |
    | Declare any local variable which the class should access
    | $instance   - the single instanciated instance of the class (signleton)
    | $connection - the connection to the database
    |
    */

    private static $instance;
    public         $connection;

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
        $conn = include "./app/core/conf/database.php";
        $this -> connection = new PDO('mysql:host='.$conn['db_host'].'; dbname='.$conn['db_name'].';charset=utf8', $conn['db_user'], $conn['db_pass']);
    }

    /*
    |--------------------------------------------------------------------------
    | Get instance
    |--------------------------------------------------------------------------
    |
    | Returns the set instance to ensure only one socket stays open.
    |
    */

    public static function getInstance()
    {
        $object = __CLASS__;
        !isset(self::$instance) ? self::$instance = new $object : false;
        return self::$instance;
    }

}