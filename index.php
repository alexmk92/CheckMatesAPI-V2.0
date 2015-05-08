<?php
    
/*
|--------------------------------------------------------------------------
| Checkmates API V2.0
|--------------------------------------------------------------------------
|
| Version 2 of the Checkmates API provides a RESTful interface to allow
| multiple clients to connect to transact with the system statelessly.
| The re-write was required to optimise back-end performance issues
| presented in version 1.0, with a long term goal to create a 
| solid back-bone for the Checkmates application.
|
| @author - Alex Sims (Checkmates CTO)
|
*/

require_once("");

/*
|--------------------------------------------------------------------------
| Abstract Interface
|--------------------------------------------------------------------------
|
| Provides an abstract interface for resources to implement when creating
| endpoints for the service. This adheres to good design practice.
|
*/

abstract class CheckmatesAPI
{
    /*
    * Property: Method
    * Defines the HTTP method that the request shall be made in (GET, PUT, POST or DELETE)
    */
    protected $method = '';
    
    /*
    * Property: Endpoint
    * The Model requested by the URI i.e. /users
    */
    protected $endpoint = '';
    
    /*
    * Property: Verb
    * Optional request parameter to further filter the results from the handler, for example
    * /users/AUS would target all Australian users
    */
    protected $verb = '';
    
    /*
    * Property: Args
    * An array of additional URI components which are appended to the endpoint to get a further
    * refined query. I.e. /<endpoint>/<verb>/<arg0>/<arg1> or /<endpoint>/<arg0>
    */
    protected $args = Array();
    
    /*
    * Property: File
    * Stores the input file from the request
    */
    protected $file = Null;
    
    /*
    --------------------------------------------------------------------------
    | Constructor: __construct
    --------------------------------------------------------------------------
    | Allows for CORS (proxy bypass) to assemble and pre-process the data recieved,
    | any other header information is set here, note that this information is 
    | only default parameters, this can be amended.
    |
    | @param $request - The request resource sent to the server.
    */
    public function __construct($request)
    {
        /*
        * Enable Cross Origin Resource Sharing (CORS) to allow clients to connect to
        * the reosurce from multiple clients, without this our API is blocked from
        * other domains.
        */
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: *");
        header("Content-Type: application/json");
        
        /*
        * Get an array of all arguments sent from the request, from this
        * we can determine the endpoint and delegate to the resource handler
        */
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);
        if(array_key_exists(0, $this->args) && !is_numeric($this->args[0]))
            $this->verb = array_shift($this->args);
            
        /*
        * PUT and DELETE requests are hidden within the $_POST object and therefore need
        * to be retrieved from the HTTP_X_HTTP_METHOD key so we can expose them to
        * delegate to the controller.
        */
        $this->method = $_SERVER['REQUEST_METHOD'];
        if($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER))
        {
            switch($_SERVER['HTTP_X_HTTP_METHOD'])
            {
                case 'PUT': 
                    $this->method = 'PUT'; break;
                case 'DELETE' : 
                    $this->method = 'DELETE'; break;
                default: 
                    throw new Exception("Unexpected Header"); break;
            }
        } 
        
        /*
        * Set the request method (GET, PUT, POST or DELETE)
        */
        switch($this->method)
        {
            case 'GET': 
                $this->_sanitiseResource($_GET); break;
            case 'PUT': 
                $this->_sanitiseResource($_GET);
                $this->file = file_get_contents("php://input"); break;
            case 'POST': 
                $this->_sanitiseResource($_POST); break;            
            case 'DELETE': break;
            default: 
                $this->_setResponse('Invalid Method', 405); break;
        }
    }
    
    /*
    |--------------------------------------------------------------------------
    | Call Resource
    |--------------------------------------------------------------------------
    |
    | Checks to see whether the concrete class implementation exists within
    | our API, if it does then delegate the request to the handler, 
    | otheriwse return a 404 response to the caller.
    |
    | This is the one public interface available to our API, as it is the
    | only facade which the user needs to be exposed too.
    |
    | @return xml/json : The response object, either XML or JSON
    |
    */
    
    public function callResource()
    {
        if ((int)method_exists($this, $this->endpoint) > 0)
            return $this->_setResponse($this{$this->endpoint}($this->args));
        else
            return $this->_setResponse("The Endpoint: $this->endpoint is invalid", 404);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Send Response
    |--------------------------------------------------------------------------
    |
    | Sends a JSON or XML response back to the client based on the result 
    | recieved from the caller method, i.e. if a resource does not
    | exist then an error 404 is returned to the client
    |
    | @param $data : The data object to be encoded and sent to client
    | @param int   : The status code to be returned, based on HTTP codes, defaults
    |                to 200 (OK)
    |
    | @return data : Either a JSON or XML object to be sent to the client, this 
    |                object will contain the resources state to conform to HATEOS
    |                principles as outlined in Roy Fieldings thesis.
    |
    */
    
    private function _setResponse($data, $statusCode = 200)
    {
        header("HTTP/1.1 " . $status . " " . $this->_getStatus($status));
        return json_encode($data);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Sanitise Resource
    |--------------------------------------------------------------------------
    |
    | Recieves an input array and sanitises the values for each of its keys,
    | this is done to ensure no malicious data is passed to the server for
    | processing as we do not want to compromise the API security. 
    |
    | This method is called recursively to sanitise each value.
    |
    | @param $data : Either an array or object to unmap or have any tags 
    |                stripped to ensure the data is secure
    |
    */
    
    private function _sanitiseResource($data)
    {
        // create ouput array
        $outputResource = Array();
        
        // check if we are on the array, if so then for each key call this
        // method recursively 
        if(is_array($data))
        {
            foreach($data as $k => $v)
            {
                $outputResource[$k] = $this->_sanitiseResource($v);
            }
        }
        // Strip the tags and return to the caller, this will eventually sanitise
        // all keys in the array
        else
        {
            $outputResource = trim(strip_tags($data));
        }
        return $outputResource;
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Request Status
    |--------------------------------------------------------------------------
    |
    | Provides a default interface to set messages status code messages for
    | any resource in the system. These can be overriden in subclasses to
    | provide more detailed information.
    |
    | @param int : The error code we want to get a message for
    | 
    | @return string : The message associated with that key
    |
    */
    
    private function _getStatus($errCode)
    {
        $message = array(
          200 => 'OK',
          401 => 'Resource not authorised',
          404 => 'Resource not found',  
          405 => 'Method not allowed',
          500 => 'Internal Server Error',
        );
        
        // Return the correct messages, assert based on the input code given
        return ($message[$errCode]) ? $message[$code] : $message[500];
    }
}