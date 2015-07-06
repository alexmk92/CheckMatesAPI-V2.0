<?php

require 'conf.php';

/*
|--------------------------------------------------------------------------
| Init
|--------------------------------------------------------------------------
|
| Initialises the application by getting configuration files and setting
| the state of the application with these values.  The files here are
| ignored from git repo to improve security, you may alter the
| configuration files as much as you wish.
|
*/

$appConf = include "./app/core/conf/app.php";
$dbConf  = include "./app/core/conf/database.php";

/*
|--------------------------------------------------------------------------
| Logging
|--------------------------------------------------------------------------
|
| Determines whether we are running the application in debug or release
| mode.  This allows us to easily modify deployment and local versions.
|
*/

if ($appConf['debug'] == true)
{
    ini_set('display_errors',1);
    ini_set('xdebug.var_display_max_depth', 200);
    ini_set('xdebug.var_display_max_children', 99999);
    ini_set('xdebug.var_display_max_data', 999999);
    error_reporting(E_ALL);
}




