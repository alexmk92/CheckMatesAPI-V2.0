<?php

/*
 * Defines how we set our notification server references up.  Note that constant
 * paths are specified in conf.php, located in the conf folder
 */

return [

    /*
	|--------------------------------------------------------------------------
	| iOS Notifications
	|--------------------------------------------------------------------------
	|
	| Configures a new iOS notification server to send push notifications
    | to.  This can be easily amended to account for production and sandbox
    | certificates.
	|
	*/

    'iOS' => [
        'serverType'    => 'APPLE',
        'certificate'   => conf(IOS_CERT_PATH,   'unset'),
        'password'      => conf(IOS_CERT_PASS,   'unset'),
        'signingServer' => conf(IOS_CERT_SERVER, 'unset')
    ],

    /*
    |--------------------------------------------------------------------------
    | Android Notifications
    |--------------------------------------------------------------------------
    |
    | Configures a new Android notification server to send push notifications
    | to.  This can be easily amended to account for production and sandbox
    | certificates.
    |
    */

    'Android' => [
        'serverType'  => 'ANDROID',
        'certificate' => conf(ANDROID_CERT_PATH, 'unset'),
        'apiKey'      => conf(ANDROID_API_KEY,   'unset'),
        'pushURL'     => conf(ANDROID_PUSH_URL,  'unset')
    ],

];