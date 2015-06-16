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

class Message
{

    /*
     |--------------------------------------------------------------------------
     | GET COMMENTS
     |--------------------------------------------------------------------------
     |
     | Get all of the comments for a checkin.
     |
     | @param $checkInId - The identifier for the checkIn.
     |
     | @param $userId    - The identifier of the user that called the endpoint.
     |
     | @return             A list of comments about a checkIn with/or a response message.
     */

    public static function getComments($checkInId, $userId)
    {

        // Get all of the comments
        $query = "SELECT ent.Entity_Id, ent.Profile_Pic_Url, ent.First_Name, ent.Last_Name, ent.Last_CheckIn_Place, comments.Content
                  FROM   checkin_comments comments
                  JOIN   entity ent
                  ON     comments.Entity_Id = ent.Entity_Id
                  WHERE  comments.Chk_Id = :checkInId
                  ";

        // Bind the parameters to the query
        $data = Array(":checkInId" => $checkInId);

        $checkInComments = Database::getInstance()->fetchAll($query, $data);

        if(!empty($checkInComments))
        {
            // Check to see if any comments are authored by a blocked user. If so remove those comments from
            // the array that will be returned as the payload.
            $query = "
                  SELECT Entity_Id2
                  FROM  friends
                  WHERE Entity_Id1 = :userId
                  AND   Category   = 4
                  ";

            // Bind the parameters to the query
            $data = Array(":userId" => $userId);

            // Get the blocked users
            $blockedUsers = Database::getInstance()->fetchAll($query, $data);

            if(!empty($blockedUsers))
            {
                // Cycle through. If we have any comments that are authored by a blocked user, then remove them
                // from the array.
                $count = 0;
                foreach ($checkInComments as $comment) {
                    foreach ($blockedUsers as $blockedUser) {

                        // If we find a blocked user, remove them from the results array.
                        if ($comment['Entity_Id'] == $blockedUser['Entity_Id2'])
                            unset($checkInComments[$count]);
                    }

                    $count++;
                }

                // Since splice didn't work, I had to use unset, which meant that the array's index's got all messed up.
                // Effectively, splice does a similar thing to this by reassigning an array, so it's not too bad of a cost
                // to use array_values to fix the indexing.
                $finalComments = array_values($checkInComments);

                return Array("error" => "200", "message" => "Results have been collected successfully.", "payload" => $finalComments);
            }
            else
                // No blocked users, so return array in normal format.
                return Array("error" => "200", "message" => "Results have been collected successfully.", "payload" => $checkInComments);
        }
         else
             // No results found.
             return Array("error" => "200", "message" => "Logic has completed successfully, but no results were found.");
    }

    /*
     |--------------------------------------------------------------------------
     | GET CHAT MESSAGES
     |--------------------------------------------------------------------------
     |
     | Get chat messages between two users. Basically a history of communications
     | between the two of them.
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
        $query = "SELECT sender, receiver, Profile_Pic_Url, First_Name, Last_Name, message, msg_dt
                  FROM   entity
                  JOIN   chatmessages
                  ON     entity.Entity_Id = chatmessages.receiver
                  WHERE  sender = :friendId
                  AND    receiver = :userId


                  UNION ALL

                  SELECT sender, receiver, Profile_Pic_Url, First_Name, Last_Name, message, msg_dt
                  FROM   entity
                  JOIN   chatmessages
                  ON     entity.Entity_Id = chatmessages.receiver
                  WHERE  sender = :userId
                  AND    receiver = :friendId

                  ORDER BY msg_dt ASC
                  ";

        // Bind the parameters to the query
        $data = Array(":userId" => $userId, ":friendId" => $friendId);

        $users = Database::getInstance()->fetchAll($query, $data);

        if(!empty($users))
        {
            // Send back the results.
            return Array("error" => "200", "message" => "Successfully retrieved messages.", "payload" => $users);
        }
        else
            // No results.
            return Array("error" => "200", "message" => "Query has executed successfully, with no results found.");
    }

    /*
     |--------------------------------------------------------------------------
     | (POST) ADD COMMENT
     |--------------------------------------------------------------------------
     |
     | Add a new comment to a checkin.
     |
     | @param $checkInId - The identifier of the checkin.
     |
     | @param $payload   - The json encoded user information: we use entityId and message.
     |
     | @return           - A success or failure message depending on the outcome.
     |
     */

    public static function addComment($checkInId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // Prepare a query that's purpose will be to add a new comment to a check in
        $query = "INSERT INTO checkin_comments(Entity_Id, Chk_Id, Content)
                  VALUES (:userId, :checkInId, :message)
                 ";

        // Bind the parameters to the query
        $data = Array(":userId" => $user['entityId'], ":checkInId" => $checkInId, ":message" => $payload['message']);

        // Perform the insert, then increment count if this wasn't a duplicate record
        if (Database::getInstance()->insert($query, $data)) {

            return Array("error" => "200", "message" => "Comment has been added successfully.");
        }
        else
            return Array("error" => "400", "message" => "Adding the new comment has failed.");
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

    public static function sendMessage($friendId, $payload)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

        // Prepare a query that's purpose will be to add a new comment to a check in
        $query = "INSERT INTO chatmessages(sender, receiver, message, msg_dt)
                  VALUES (:sender, :receiver, :message, :todaysDate)
                 ";

        // Today's date and time.
        $now = gmdate('Y-m-d H:i:s', time());

        // Bind the parameters to the query
        $data = Array(":sender" => $user['entityId'], ":receiver" => $friendId, ":message" => $payload['message'], ":todaysDate" => $now);

        // Perform the insert
        if (Database::getInstance()->insert($query, $data)) {

            // If insert succeeded.
            return Array("error" => "200", "message" => "Message has been sent successfully.");
        }
        else
            // If insert failed.
            return Array("error" => "400", "message" => "Message sending has failed.");
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

    public static function reportEmail($payload, $reportId)
    {
        // Authenticate the token.
        $user = session::validateSession($payload['session_token'],$payload['device_id']);

        // Check to see if the user has been retrieved and the token successfully authenticated.
        if(empty($user))
            return Array("error" => "400", "message" => "Bad request, please supply JSON encoded session data.", "payload" => "");

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

        echo $body;

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
            return Array("error" => "400", "message" => "Error sending email.");

    }
}