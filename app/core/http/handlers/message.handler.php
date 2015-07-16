<?php

namespace Handlers;
use Models\Database;

/*
|--------------------------------------------------------------------------
| Message Handler
|--------------------------------------------------------------------------
|
| Defines the implementation of a Message handler, this is a simple interface
| to converse with the Database.  It has been abstracted from the endpoint
| implementation as there may be a large number of queries within the file.
|
| @author - Alex Sims (Checkmates CTO) + Adam Stevenson
|
*/

// Include the session handler object
require_once "./app/core/http/handlers/session.handler.php";
require_once "./app/core/http/api.push.server.php";

class Message
{


    /*
     |--------------------------------------------------------------------------
     | GET CHAT MESSAGES
     |--------------------------------------------------------------------------
     |
     | Get chat messages between two users. Basically a history of communications
     | between the two of them.
     |
     | @header entityId  - The identifier of the user. GET request does not allow for
     |                     a JSON body.
     |
     | @params $friendId - The Identifier of the friend.
     |
     | @params $userId   - The identifier of the individual that made the request.
     |
     | @return           - A response message with a payload of messages.
     |
     */

    public static function getChatMessages($friendId, $userId)
    {
        // Get all of the comments between the two people.
        $query = "SELECT mid, sender, receiver, Profile_Pic_Url, First_Name, Last_Name, message, msg_dt
                  FROM   entity
                  JOIN   chatmessages
                  ON     entity.Entity_Id = chatmessages.sender
                  WHERE  sender = :friendId
                  AND    receiver = :userId

                  UNION ALL

                  SELECT mid, sender, receiver, Profile_Pic_Url, First_Name, Last_Name, message, msg_dt
                  FROM   entity
                  JOIN   chatmessages
                  ON     entity.Entity_Id = chatmessages.sender
                  WHERE  sender = :userId
                  AND    receiver = :friendId

                  ORDER BY msg_dt ASC
                  ";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        $messages = Database::getInstance()->fetchAll($query, $data);

        if(!empty($messages))
        {
            // Send back the results.
            return Array("error" => "200", "message" => "Successfully retrieved messages.", "payload" => json_decode(json_encode($messages), JSON_UNESCAPED_UNICODE));
        }
        else
            // No results.
            return Array("error" => "203", "message" => "Query has executed successfully, with no results found.", "payload" => Array("messages" => "[]"));
    }

    /*
     |--------------------------------------------------------------------------
     | GET CONVERSATIONS
     |--------------------------------------------------------------------------
     |
     | For every conversation the user is involved with, get the first and last name of
     | the friend. Accounts for blocked users.
     | If anyone wants this behaviour changed, let me know - Adam.
     | The thought process is that users will unblock people and then they will appear.
     |
     | Note: The two users need to be friends. Also, they must have at least one message that
     |       has been sent between the two of them.
     |
     | @params $userId - The identifier of the user.
     |
     | @return         - Information about each user that the individual is talking to.
     |
     */

    public static function getConversations($userId)
    {
        // Construct a query that will collect all of the entity information about users
        // that are in a conversation with each other.
        $query = "
                  SELECT * FROM
                  (

                      SELECT Entity_Id, First_Name, Last_Name, Profile_Pic_Url, mid, message, msg_dt
                      FROM   entity
                      JOIN   friends
                      ON     Entity_Id1 = Entity_Id OR Entity_Id2 = Entity_Id
                      JOIN   chatmessages
                      ON     entity.Entity_Id = chatmessages.sender
                      WHERE  receiver = :userId
                      AND    Category != 4

                      UNION ALL

                      SELECT Entity_Id, First_Name, Last_Name, Profile_Pic_Url, mid, message, msg_dt
                      FROM   entity
                      JOIN   friends
                      ON     Entity_Id2 = Entity_Id OR Entity_Id1 = Entity_Id
                      JOIN   chatmessages
                      ON     entity.Entity_Id = chatmessages.receiver
                      WHERE  sender = :userId
                      AND    Category != 4

                      ORDER BY mid DESC

                  ) initialResults

                  GROUP BY Entity_Id
                  ORDER BY msg_dt DESC

                  ";

        // Bind the parameters to the query.
        $data = Array(":userId" => $userId);

        $conversations = Database::getInstance()->fetchAll($query, $data);

        if(count($conversations) > 0)
            return Array("error" => "200", "message" => "Successfully retrieved all conversations.", "payload" => $conversations);
        else
            return Array("error" => "203", "message" => "Query has returned with no conversations found. Either the "
            ."two users are not friends, or no messages have been sent.", "payload" => Array("conversations" => "[]"));
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) SEND MESSAGE
     |--------------------------------------------------------------------------
     |
     | Add a new message. This will be between two people. The entity id passed
     | through the payload will be the sender, and the friendId will be the receiver.
     |
     | @param friendId - The friend or receiver of the message.
     |
     | @param payload  - The json encoded packet for recieving the entiy_id etc.
     |
     | @return         - A success or failure message depending on the outcome.
     |
     */

    public static function sendMessage($friendId, $payload, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user) || empty($payload["message"]))
            return Array("error" => "400", "message" => "Bad request, the JSON object sent to the server was invalid or it did not contain the appropriate keys", "payload" => "");

        // Prepare a query that's purpose will be to add a new comment to a check in
        $query = "INSERT INTO chatmessages(sender, receiver, message, msg_dt)
                  VALUES (:sender, :receiver, :message, :todaysDate)
                 ";

        // Today's date and time.
        $now = gmdate('Y-m-d H:i:s', time());

        // Convert the message in the payload to UTF-8
        $message = $payload["message"];


        // Bind the parameters to the query
        $data = Array(":sender" => $user['entityId'], ":receiver" => $friendId, ":message" => $message, ":todaysDate" => $now);
        $res  = Database::getInstance()->insert($query, $data);
        // Perform the insert
        if ($res > 0) {

            // Build a response object
            $details = Array("message_id" => $res, "message" => $message, "sent" => $now);

            // Today's date and time.
            $now = gmdate('Y-m-d H:i:s', time());

            // Configure the push payload, we trim the name so that if it was Alexander John, it becomes Alexander.
            $pushPayload = Array(
                "senderId" => $user['entityId'],
                "senderName" => $user['firstName'] . " " . $user['lastName'],
                "receiver" => $friendId,
                "message" => $user['firstName']. " " . $user['lastName'] . " sent you a message.",
                "type" => 2,
                "date" => $now,
                "messageId" => $res,
                "messageType" => 2,
                "sentMessage" => $payload["message"]
            );

            // Reference a new push server and send the notification.
            $server = new \PushServer();
            $res = $server->sendNotification($pushPayload);

            if(!empty($res))
                // Request and notification (push) sent.
                return Array("error" => "200", "message" => "The message was sent successfully.", "payload" => $details);

            else
                // Only request sent.
                return Array("error" => "207", "message" => "Partial success: The message was sent successfully, but without a notification", "payload" => $details);
        }
        else
            // If insert failed.
            return Array("error" => "500", "message" => "Message sending has failed.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) REPORT EMAIL
     |--------------------------------------------------------------------------
     |
     | Retrieve the message and user id from the payload and send an email to
     | @mycheckmates.
     |
     | No parameters required, but the following are retrieved from the raw json body.
     |
     | Message:   The message to report.
     |
     | Entity_Id: The identifier of the user.
     |
     | @return A success message dependent on whether or not the email sent successfully.
     |
     */

    public static function reportEmail($payload, $reportId, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "Your session_token and/or device_id combination was not found in the DB. Login again", "payload" => "");

        // Collect the information about the reporter and the reported.
        $query = "
                  SELECT Entity_Id, First_Name, Last_Name, Email
                  FROM  entity
                  WHERE Entity_Id = :userId

                  UNION ALL

                  SELECT Entity_Id, First_Name, Last_Name, Email
                  FROM  entity
                  WHERE Entity_Id = :reportId
                  ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":reportId" => $reportId);

        // Get the blocked users
        $users = Database::getInstance()->fetchAll($query, $data);

        // Array index 0 is user who sent report.
        // Array index 1 is the reported user.
        $sender = $users[0];
        $reported = $users[1];

        $body = '<html>
                                    <body>
                                        <p><strong><span style="font-size: 24px;">Report</span></strong></p>
                                        <p><strong><span style="font-size: 14px;">- Reporter Details</span></strong></p>
                                        <table style="text-align: center;background:#C2C2C2;border-spacing:1px" cellspacing="1">
                                            <tbody>
                                                <tr style="background:#ECECEC"><td style="width:150px;"><p>Id</p></td><td style="width:150px;"><p>' . $sender['Entity_Id'] . '</p></td></tr>
                                                <tr style="background:#ECECEC"><td><p>Full Name</p></td><td><p>' . $sender['First_Name'] . ' ' . $sender['Last_Name'] . '</p></td></tr>
                                                <tr style="background:#ECECEC"><td><p>Email</p></td><td><p>' . $sender['Email'] . '</p></td></tr>
                                            </tbody>
                                        </table>
                                        <p><br /></p>
                                        <p><strong><span style="font-size: 14px;">- Reported Details</span></strong></p>
                                        <table style="text-align: center;background:#C2C2C2;border-spacing:1px" cellspacing="1">
                                            <tbody>
                                                <tr style="background:#ECECEC"><td style="width:150px;"><p>Id</p></td><td style="width:150px;"><p>' . $reported['Entity_Id'] . '</p></td></tr>
                                                <tr style="background:#ECECEC"><td><p>Full Name</p></td><td><p>' . $reported['First_Name'] . ' ' . $reported['Last_Name'] . '</p></td></tr>
                                                <tr style="background:#ECECEC"><td><p>Email</p></td><td><p>' . $reported['Email'] . '</p></td></tr>
                                            </tbody>
                                        </table>
                                        <p><br /></p>
                                        <p><strong><span style="font-size: 14px;">&nbsp; ' . $payload['message'] . '</span></p>
                                </body>
                            </html>';

        // Send the message to issues@mycheckmates.com
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
        $headers .= 'From: ReportService@checkmates.com' . "\r\n";

        $flag = mail('issues@mycheckmates.com', 'Report', $body, $headers);
        if ($flag)
            // If the email has been sent successfully.
            return Array("error" => "200", "message" => "You have successfully reported this user.");
        else
            // If email has not sent successfully.
            return Array("error" => "500", "message" => "Error sending email.");

    }

    /*
     |--------------------------------------------------------------------------
     | (DELETE) Delete Message
     |--------------------------------------------------------------------------
     |
     | Delete a message in a conversation between two people.
     |
     | @param $messageId - The identifier of the message.
     |
     | @return A success message dependent on whether or not the message was deleted successfully.
     |
     */

    public static function deleteMessage($messageId, $user)
    {
        if(empty($user))
            return Array("error" => "401", "You are not authorised to delete this message as you did not send it.");

        // Prepare a query that's purpose will be to delete one message.
        $query = "DELETE FROM chatmessages WHERE mid = :messageId AND sender = :senderId";

        // Bind the parameters to the query
        $data = Array(":messageId" => $messageId, $user["entityId"]);

        // Delete a message using the mid.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "The message has been removed from the conversation.");
        else
            return Array("error" => "401", "message" => "You are not authorised to delete this message as you did not send it.");

    }

    /*
     |--------------------------------------------------------------------------
     | (DELETE) Delete All Message
     |--------------------------------------------------------------------------
     |
     | Delete all messages in a conversation between two people.
     |
     | @param $friendId - The identifier of the friend.
     |
     | @param $payload  - The json encoded HTTP body.
     |
     | @return          - A success message dependent on whether the conversation
     |                    was successfully deleted or not.
     |
     */

    public static function deleteConversation($payload, $friendId, $user)
    {
        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "401", "message" => "You are not authorised to delete this conversation as you did not create it.");

        // Prepare a query that's purpose will be to delete all conversation records between two people.
        $query = " DELETE FROM chatmessages
                   WHERE receiver = :userId   AND sender = :friendId
                   OR    receiver = :friendId AND sender = :userId
                   ";
 
        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":friendId" => $friendId);

        // Delete all records of friendship between the two users.
        // If the query runs successfully, return a success 200 message.
        if (Database::getInstance()->delete($query, $data))
            return Array("error" => "200", "message" => "The conversation has been deleted successfully.");
        else
            return Array("error" => "401", "message" => "You are not authorised to delete this conversation as you did not create it.");


    }
}