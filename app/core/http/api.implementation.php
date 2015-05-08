<?php

/*
|--------------------------------------------------------------------------
| Checkmates API V2.0
|--------------------------------------------------------------------------
|
| Defines the implementation of the API, this will delegate calls to
| the specific endpoint resource based on the inbound request and
| request type (GET, PUT, POST or DELETE) - it will also handle
| responses and shall produce HATEOS formatted objects.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

require 'api.abstract.php';
require './app/core/models/User.php';
require './app/core/models/APIKey.php';

/*
|--------------------------------------------------------------------------
| Implementation
|--------------------------------------------------------------------------
|
| Create the API class which will handle all requests for the application,
| this is the entry point of any request and will delegate calls to
| other resources in the system. Hooray for clean code!
|
*/

class CheckmatesAPI extends API
{
	/*
	* Property: User
	* The user object will be set via the user Model class, this will validate
	* transactions to the API by ensuring that a valid API Key and Session 
	* token have been provided by the authenticating party.
	*/
	
	protected $User;
	
	/*
	|--------------------------------------------------------------------------
	| Method: Constructor
	|--------------------------------------------------------------------------
	|
	| Constructs a new implementation of the CheckmatesAPI, this will allow us
	| to begin interacting with our resources based on the inbound request.
	|
	| @param $request : The request header to be build
	| @param $origin  : Where was the request sent from 
	|
	*/
	
	public function __construct($request, $origin)
	{
		/*
		* Call the constructor in the abstract class to build the request header
		* and determine which endpoint we need to access
		*/
		
		parent::__construct($request);
	
		/*
		* Define references to any models that are to be used by the API, by 
		* default here we need an API key and a User to authenticate
		*/
		
		$User   = new Models\User();
		$APIKey = new Models\APIKey();
		
		/*
		* Ensure that our request can be validated by checking the request header
		* for a valid API key and session token
		*/

		if(!array_key_exists('apiKey', $request))
			throw new Exception('No API Key provided for this resource');
		else if (!$APIKey->verifyKey($request['apiKey'], $origin))
			throw new Exception('This API Key is not valid');
		else if (array_key_exists('token', $request) && !$User->get('token', $request['token']))
			throw new Exception('The token provided by the user was invalid');
			
		/*
		* Connect to the resource server using the credentials provided by the calling
		* client. This client is valid after passing the checks above.
		*/
		
		$this->User = $User;
	}
	
	
}