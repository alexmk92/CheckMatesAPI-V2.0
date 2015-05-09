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

define(DB_HOST, 'localhost');
define(DB_NAME, 'kinekt');
define(DB_USER, 'root');
define(DB_PASS, '7O5VZJit9S');

/*
 * Notification server configuration
 */

define(ANDROID_CERT_PATH, './public/certificates/iOS.cert');
define(ANDROID_API_KEY,   'AIzaSyBgqhtPTbtxjZ9mxAr7xThiCiR5qT4oRzg');
define(ANDROID_PUSH_URL,  'http://android.googleapis.com/gcm/send');

define(IOS_CERT_PATH,     './public/certificates/android.cert');
define(IOS_CERT_PASS,     'kinektmates15');
define(IOS_CERT_SERVER,   'ssl://gateway.push.apple.com');

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

