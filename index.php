<?php



require './app/core/conf/loader.php';



/*
|--------------------------------------------------------------------------
| Checkmates API V2.0
|--------------------------------------------------------------------------
|
| Index.php is the main "Engine" space for the application, it is 
| responsible for accepting the inbound request and then delegating a 
| call to the API based whether or not a request had even been made.
|
| No security processing is done in this part of the script, it is simply
| the public client interface which anybody can connect too.
|
| We start by checking for local requests (requests made on the same server)
| if this is the case, CORS is not implemented. We then attempt to call
| the abstract API interface via "_callResource", this will send a 
| payload back to the client which can be worked with.
|
*/

require './app/core/http/api.implementation.php';

if (!array_key_exists('HTTP_ORIGIN', $_SERVER))
{
	$_SERVER['HTTP_ORIGIN'] == $_SERVER['SERVER_NAME'];
}

/*
* Finally, we make the call to the API and echo the response back to the
* client. This payload is in JSON format (could be extended for XML),
* if an error is thrown it is printed via the Exception, these 
* exceptions are thrown from the concrete API implementation
*/

try
{
	$API = new CheckmatesAPI($_REQUEST['request'], $_SERVER['HTTP_ORIGIN']);
	echo $API->_callResource();
} 
catch (Exception $e)
{
	echo json_encode(Array('error' => $e->getMessage()));
}
