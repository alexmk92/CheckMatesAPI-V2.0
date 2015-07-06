<?php

use Models\Database;

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

    /*
    |--------------------------------------------------------------------------
    | Send Notification
    |--------------------------------------------------------------------------
    |
    | Public interface exposed to the client, this will send a push notification
    | to the specified server for users to see.
    |
    */

    public static function sendNotification($payload)
    {

        // List of Android and iOS Devices for this user, push notifications will be sent to each
        // device that the user owns.
        $androidDevices = Array();
        $iOSDevices     = Array();

        // Determine what device the payload should be sent to, this is because Android and Apple
        // hand push notifications differently, we shall also delete all out-dated queries here.
        $query = "DELETE FROM notifications WHERE TIMESTAMPDIFF(DAY, notif_dt, :now) > 3";
        $data  = Array(":now" => gmdate('Y-m-d H:i:s', time()));

        Database::getInstance()->delete($query, $data);

        // Configure the base information for payload on the server before sending
        if($payload["type"] == 3 || $payload["type"] == 5 || $payload["type"] = 6 || $payload["type"] == 7)
        {
            $query = "
                        INSERT INTO notifications
                        (
                            notif_type, sender, receiver, message, notif_dt, ref
                        )
                        VALUES
                        (
                            :type,
                            :sender,
                            :receiver,
                            :message,
                            :chkDate,
                            :ref
                        )
                     ";

            $data = Array(
                ":type"     => $payload["type"],
                ":sender"   => $payload["senderId"],
                ":receiver" => $payload["receiver"],
                ":message"  => $payload["message"],
                ":chkDate"  => $payload["date"],
                ":ref"      => $payload["messageId"]
            );
            Database::getInstance()->insert($query, $data);
        }

        // Find a list of all destination devices
        $query = "SELECT DISTINCT type, push_token FROM user_sessions WHERE oid = :entityId AND loggedIn = 1 AND LENGTH(push_token) > 63";
        $data  = Array(":entityId" => $payload["receiver"]);

        $devices = Database::getInstance()->fetchAll($query, $data);
        if(count($devices) > 0)
        {
            $return = Array();

            foreach($devices as $device)
            {
                switch ($device["type"]) {
                    case 1:
                        $return = self::sendApplePush($payload, $device["push_token"]);
                        break;
                    case 2:
                        $return = self::sendAndroidPush($payload, $device["push_token"]);
                        break;
                    default:
                        break;
                }
            }

            return $return;
        }
        else
        {
            return Array("error" => "400", "message" => "Sorry, the push notification was not sent. This is caused by an invalid push token, or if the user is offline");
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

    private static function sendApplePush($payload, $deviceToken)
    {
        // Configure our socket connection using constants found in app/conf.php
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', IOS_CERT_PATH);
        stream_context_set_option($ctx, 'ssl', 'passphrase', IOS_CERT_PASS);

        // Check if we could open our socket, if we could attempt to send the push else close the socket and return
        $apns_fp = stream_socket_client(IOS_CERT_SERVER, $err, $errStr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
        if($apns_fp)
        {
            if(empty($payload["messageType"]))
                $payload["messageType"] = 0;
            if(empty($payload["messageId"]))
                $payload["messageId"] = 0;

            // Create the payload and then compress it so it can be sent securely over the network
            $body['aps'] = Array(
                "content-available" => 1,
                "alert"             => $payload["message"],
                "sound"             => "default",
                "nt"                => $payload["type"],
                "sid"               => $payload["senderId"],
                "sname"             => $payload["senderName"],
                "dt"                => $payload["date"],
                "mt"                => $payload["messageType"],
                "mid"               => $payload["messageId"]
            );
            $pushPayload = json_encode($body);
            $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($pushPayload)) . $pushPayload;

            // Write the message to the server, this will then be routed to our receiver
            $result = fwrite($apns_fp, $msg, strlen($msg));

            // Dispose of any open sockets / resources
            fclose($apns_fp);

            // Return the result to the client
            if(!$result)
                return Array("error" => "417", "message" => "Failed to send push.");
            else
                return Array("error" => "203", "message" => "Push sent successfully");
        }
        else
        {
            // Dispose of any open sockets / resources
            fclose($apns_fp);

            return Array("error" => "417", "message" => "Sorry, we couldn't connect to Apple when sending the push...Check certificates.");
        }
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

    private static function sendAndroidPush($payload, $pushToken)
    {
        if(empty($payload["messageType"]))
            $payload["messageType"] = 0;
        if(empty($payload["messageId"]))
            $payload["messageId"] = 0;

        $data = Array(
            "payload"    => $payload["message"],
            "type"       => $payload["type"],
            "sid"        => $payload["senderId"],
            "sname"      => $payload["senderName"],
            "dt"         => $payload["date"],
            "mt"         => $payload["messageType"],
            "mid"        => $payload["messageId"]
        );
        $body = Array("registration_ids" => $pushToken, "data" => $data);

        $header = Array("Authorization: key=" . ANDROID_API_KEY,
                        "Content-Type: application/json");

        // Open the connection then set the URL to respoind to with POST data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, ANDROID_PUSH_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        // Close the curl pipeline and then examine result to write to client
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if($res["success"] >= 1)
            return Array("error" => "203", "message" => "Successfully sent push notification.");
        else
            return Array("error" => "400", "message" => "Failed to send android push notification");
    }
}