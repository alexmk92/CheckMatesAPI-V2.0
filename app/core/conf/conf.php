<?php

/*
|--------------------------------------------------------------------------
| Config.php
|--------------------------------------------------------------------------
|
| This is a private configuration file to define application wide constants,
| it should be .gitignore to prevent people from discovering configuration
| details for the API's sensitive data.
|
*/

/*
 * Database configuration
 */

define(DB_HOST, '146.185.147.142');
define(DB_NAME, 'root');
define(DB_USER, 'root');
define(DB_PASS, 'test');

/*
 * Notification server configuration
 */

define(ANDROID_CERT_PATH, './public/certificates/iOS.cert');
define(IOS_CERT_PATH, './public/certificates/android.cert');

/*
|--------------------------------------------------------------------------
| Test constants
|--------------------------------------------------------------------------
|
| Tests the value of the constant, if its null or empty then return a
| default value specified by the client.
|
*/

function conf($x, $out)
{
    if (!isset($x) || empty($x))
    {
        return $out;
    }
    return $x;
}

