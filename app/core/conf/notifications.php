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
        'certificate' => conf(IOS_CERT_PATH, 'unset')

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
        'certificate' => conf(ANDROID_CERT_PATH, 'unset')
    ],

];