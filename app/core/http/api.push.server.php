<?php

/*
|--------------------------------------------------------------------------
| Push Server
|--------------------------------------------------------------------------
|
| Defines the APIs push notification server, this will handle sending a
| push notification to a specific device, set of devices or platform
| based on the data sent by the client.
|
| The push server configuration payload can be set in conf.php located
| in the conf directory.
|
*/

class PushServer
{

    protected $config;
    protected $serverType;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    |
    | Builds a new Push notification server based on the configuration payload
    | sent by the caller.  This class handles all push notifications sent to
    | devices.
    |
    */

    public function __construct($configuration)
    {
        $this->config     = $configuration;
        $this->serverType = $configuration['serverType'];
    }

    /*
    |--------------------------------------------------------------------------
    | Send Notification
    |--------------------------------------------------------------------------
    |
    | Public interface exposed to the client, this will send a push notification
    | to the specified server for users to see.
    |
    | If a request is to be sent to both Android and iOS devices, we must create
    | another PushServer object.
    |
    */

    public function sendNotification($payload)
    {
        switch($this->serverType)
        {
            case "APPLE"   : sendApplePush($payload);
                break;
            case "ANDROID" : sendAndroidPush($payload);
                break;
            default        : throw new Exception($this->serverType . " is not supported by the push server.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Apple Push
    |--------------------------------------------------------------------------
    |
    | Sends a new push notification to the registered APN.
    |
    | @param $payload - The JSON payload containing the info to be sent to the
    |                   receiving client.
    |
    | @return $status - The request code returned from the service, either
    |                   200 if the request was sent, else 400.
    |
    */

    private function sendApplePush($payload)
    {

    }

    /*
    |--------------------------------------------------------------------------
    | Android Push
    |--------------------------------------------------------------------------
    |
    | Sends a new push notification to the registered APN.
    |
    | @param $payload - The JSON payload containing the info to be sent to the
    |                   receiving client.
    |
    | @return $status - The request code returned from the service, either
    |                   200 if the request was sent, else 400.
    |
    */

    private function sendAndroidPush($payload)
    {

    }
}