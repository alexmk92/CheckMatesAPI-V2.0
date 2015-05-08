<?php

return
[

    /*
	|--------------------------------------------------------------------------
	| Application Debugging
	|--------------------------------------------------------------------------
	|
	| When true, this will enable PHP to write error messages back to the
    | client.  This should only be used in production, and never in
    | release.
	|
	*/

    'debug' => true,

    /*
	|--------------------------------------------------------------------------
	| Application Request Endpoints
	|--------------------------------------------------------------------------
	|
	| Defines a map of HTTP Handlers which will be extended from the api
    | implementation file.
	|
	*/

    'handlers' => [
        'User'     => 'user.handler.php',
        'Key'      => 'key.endpoint.php',
        'Message'  => 'message.endpoint.php',
        'Checkin'  => 'checkin.endpoint.php'
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Resource Managers
    |--------------------------------------------------------------------------
    |
    | Defines a map of resource managers.  A resource manager is responsible
    | for conversing with the database, whereas a HTTP handler is responsible
    | for invoking a method on the manager.
    |
    | The system has been architected this way to prevent the model from
    | being too bloated.
    |
    */

    'managers' => [
        'User'    => 'UserManager.php',
        'Key'     => 'KeyManager.php',
        'Message' => 'MessageManager.php',
        'Checkin' => 'CheckinManager.php'
    ],

];