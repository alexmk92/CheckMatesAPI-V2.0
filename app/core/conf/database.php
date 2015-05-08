<?php

return
[
    /*
	|--------------------------------------------------------------------------
	| PDO Style
	|--------------------------------------------------------------------------
	|
	| Sets the default fetch style for the PDO client, it can be fine tuned
    | later in the application, but for now we will always fetch ASSOC
    | arrays from the client.
	|
	*/

    'fetch' => PDO::FETCH_ASSOC,

    /*
	|--------------------------------------------------------------------------
	| Client
	|--------------------------------------------------------------------------
	|
	| Initialises the MySQL Client we will be using to connect to the database
	|
	*/

    'client' => [
        'db_driver' => 'mysql',
        'db_host'   => '',
        'db_name'   => '',
        'db_user'   => '',
        'db_pass'   => '',
        'charset'   => 'utf8',
        'prefix'    => '',
        'schema'    => 'public'
    ],

];