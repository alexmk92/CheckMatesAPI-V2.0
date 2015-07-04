# CheckMates API - Version 2.0
The Checkmates API is being redeisgned as a RESTful web API to allow the application interface to be tapped into 
more easily.  The previous system is riddled with many bugs which cause strange discrepancies when pulling 
information back from the web service.  

Below is a brief overview of the Endpoints (interfaces) available to use in the API, this is a very brief overview
and a full developer reference will be provided in future.

Currently all information returned from the API is in JSON, in future I may add the capability to serialise to XML for
wider support, but it is not a huge priority.

**IMPORTANT - PLEASE READ** All requests must now include a `session_token` and `device_id`.  It is no longer a requirement to send a `session_token` and `device_id` in the body of any request. Providing these will have no effect on the result of a query.

## User Endpoint
All user requests are documented here:

##### GET
1) All users can be retrieved via`/api/v2/User`.  

2) A specific user can be retrieved via `/api/v2/User/{userId}`.

3) All users who checked in at a specific location can be retrieved at `/api/v2/User/at-location/{long}/{lat}`.

An example request require the following header to be sent:

```json
{
    "session_token" : "some_token",
    "deivce_id" : "some_id"
}
```

The parameters sent in the URI will formulate the rest of the request.  You can, expect a `401` response if you provided an invalid `session_token` or `device_id`, if not one of the following responses should be expected:

```json
{
    "error": 200,
    "message": "Congratulations, you are the first person to check into this location",
    "data": "No data available for this resource."
}
```

The above describes an empty result set

```json
{
    "error": 200,
    "message": "Successfully retrieved 2 around your location.",
    "data": [
        "users" : 
        [
            {
                "Entity_Id": "1535",
                "first_name": "Davis",
                "last_name": "",
                "profile_pic": "https://scontent.xx.fbcdn.net/hphotos-xfp1/v/t1.0-9/p180x540/10364032_1450745385243813_2041085208857430323_n.jpg?oh=0f8971a5ba744ef6b2edafabce813e58&oe=5627CB8B",
                "last_country": "United Kingdom",
                "last_checkin_date": "2015-06-17 12:16:45",
                "last_checkin_lat": "51.5099",
                "last_checkin_long": "-0.133541",
                "Sex": "1",
                "Age": null,
                "distance": "0",
                "FC": "3"
            },
            {
                "Entity_Id": "1518",
                "first_name": "Jason",
                "last_name": "",
                "profile_pic": "https://scontent.xx.fbcdn.net/hphotos-xpa1/v/l/t1.0-9/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
                "last_country": null,
                "last_checkin_date": "2015-06-07 06:49:31",
                "last_checkin_lat": "",
                "last_checkin_long": "",
                "Sex": "1",
                "Age": "22",
                "distance": "5727.653311770566",
                "FC": "3"
            }
        ]
    ]
}
```

4) A Users notifciations can be retrieved at: `/api/v2/User/notifications/{userId}` 

Example response:

```json
{
    "error": 200,
    "message": "You have 1 new notifications!",
    "data": [
        {
            "notif_id": "3079",
            "notif_type": "3",
            "sender": "1531",
            "receiver": "1527",
            "message": "Davis Allie wants to add you as a friend.",
            "notif_dt": "2015-06-15 12:29:58",
            "ref": null
        }
    ]
}
```

OR

```json
{
    "error": 404,
    "message": "No new notifications.",
    "data": "No data available for this resource."
}
```

The above describes a result set limited to 2 results to save bandwidth, this was requested using: `http://alexsims.me/checkmates/api/v2/User/at-location/-0.133541/51.5099/2`

##### PUT
1) A user can be updated by sending a new JSON object with the new information to `/api/v2/User/update-profile/{userId}`. Please note that the JSON object sent must contain all information on the given user.  This resource does not need to be called directly as changes set here are automatically performed when the user signs up or logs in.
However, in the event that you need to update a users about information, you can send a JSON object with any amount of the keys listed below, but you must always provided a `dob` key, otherwise a 400 error will be returned:

```json
$validKeys = Array("first_name", "
                    last_name", 
                    "profile_pic_url", 
                    "entity_id", 
                    "fb_id", 
                    "email", 
                    "dob", 
                    "sex", 
                    "about", 
                    "create_dt", 
                    "last_checkin_lat", 
                    "last_checkin_long",
                    "last_checkin_place", 
                    "last_checkin_dt", 
                    "score",
                    "score_flag", 
                    "image_urls");
```

Keys not provided in this format will result in those fields not being updated, this is due to the method constructing the UPDATE query based on the keys given in the json object (these keys directly map to the database field names).
A 400 error will be returned if the dob calculates to being less than 18 years of age.

An example request may look like:

```json    
{
    "first_name" : "John",
    "last_name" : "Doe",
    "dob" : "Y-m-d",
    "about" : "some details",
}
```

2) A users position can be updated by specifying the new long/lat in the URI via: `/api/v2/User/update-location/{lat}/{long}` in addition to the longitude and latitude sent in the URL, a JSON object containing session information must be provided.

```json
{
    "device_id" : "some_id",
    "session_token" : "some_token"
}
```
If this information is not correct then a 401 will be returned. An error 500 may also be returned if the resource does not update, this normally means that the Database could not be reached.

3) A Users preferences can be updated at the URI: `/api/v2/User/update-preferences/{userId}` as usual a `session_token` and `device_id` must be provided in the HTTP header to authenticate the request.  In addition to this a JSON object must be sent in the request body that contains at least one value from the preferences table either:

1) Pref_Facebook : 1 = Visible to facebook friends, 0 = not visible to facebook friends

2) Pref_Kinekt : 1 = Visible to kinekt friends, 0 = not visible to kinekt friends

3) Pref_Everyone : 1 = Visible to everybody, 0 = visible to nobody

4) Pref_Sex : 1 = only show males, 2 = only show females, 3 = show both sexes

5) Pref_Lower_Age = The lower age boundary, if < 18 it will default to 18

6) Pref_Upper_Age = The upper age boundary, if > 50 it will default to 50, but will change to 110 on queries for erroneous age groups

7) Pref_Chk_Exp = The duration in hours for your Checkins to expire, either 1, 4 or 8 can be sent. 

If any of the values supplied are the same as a value present in the database, then an error `400` will be returned due to the UPDATE query failing as the rows were the same.  To avoid this please only send CHANGED values.   Finally, if you provide a `userId` in the URI that does not match the `userId` that the session belongs to, then a `401` error will be returned, this dictates that you are trying to modify somebody elses preferences. **IMPORTANT** The key values here are case sensitive so please ensure the input payload matches the parameters above, failure to do so will result in a `400` with the response:

```json
{
    "error": 400,
    "message": "Updating has failed because at least one preferences tag needs to be provided, please check your input payload as it is case sensitive.  i.e. Pref_Facebook is not the same as pref_facebook",
    "data": "No data available for this resource."
}
```

Example Input Payload:

```json
{
    "Pref_Facebook" : "1",
    "Pref_Kinekt" : "1"
}
```

Example Response:

```json
{
    "error": 409,
    "message": "The values could not be changed as they match existing values in the system.",
    "data": "No data available for this resource."
}
```

The above occurs when the one or more parameters are the same as what currently exist in the DB

``` json
{
    "error": 200,
    "message": "The preferences have been changed successfully.",
    "data": "No data available for this resource."
}
```

If all values provided are unique, then the preferences will update successfully.

Finally, note that more than one parameter can be sent, you need not limit yourself.

4) A Users settings can be updated at the URI: `/api/v2/User/update-preferences/{userId}` as usual a `session_token` and `device_id` must be provided in the HTTP header to authenticate the request.  In addition to this a JSON object must be sent in the request body that contains at least one value from the preferences table either:

1) privacyCheckin : Either 1 or 2 where 1 means only friends can view checkins and 2 means everyone can
  
2) privacyVisibility : 1 = 500km radius, 2 = countrywide radius, 3 = worldwide radius - This determines who can see you on the map

3) notifTag : 1 = Recieve tag notifications, 0 = dont recieve tag notifications

4) notifMessages : 1 = Receive message notifciations, 0 = dont receive message notifcations

5) notifNewFriends : 1 = Receive notifications for having a friend request accepted, 0 = dont receive notifications for this

6) notifFriendRequests : 1 = Receive notifications for being added as a friend, 0 = dont receive notifications for this

7) notifCheckinActivity : 1 = Receive notifications for when something happens on a checkin you were a part of (comment or like etc)

8) listVisibility : 1 = Visible on list view to everybody, 2 = Visible on listview to Friends, 3 = Hidden from list view

If any of the values supplied are the same as a value present in the database, then an error `400` will be returned due to the UPDATE query failing as the rows were the same.  To avoid this please only send CHANGED values.   Finally, if you provide a `userId` in the URI that does not match the `userId` that the session belongs to, then a `401` error will be returned, this dictates that you are trying to modify somebody elses preferences. **IMPORTANT** The key values here are case sensitive so please ensure the input payload matches the parameters above, failure to do so will result in a `400` with the response:

```json
{
    "error": 400,
    "message": "Updating has failed because at least one preferences tag needs to be provided, please check your input payload as it is case sensitive.  i.e. Pref_Facebook is not the same as pref_facebook",
    "data": "No data available for this resource."
}
```

Example Input Payload:

```json
{
    "Pref_Facebook" : "1",
    "Pref_Kinekt" : "1"
}
```

Example Response:

```json
{
    "error": 409,
    "message": "The values could not be changed as they match existing values in the system.",
    "data": "No data available for this resource."
}
```

The above occurs when the one or more parameters are the same as what currently exist in the DB

``` json
{
    "error": 200,
    "message": "The preferences have been changed successfully.",
    "data": "No data available for this resource."
}
```

If all values provided are unique, then the preferences will update successfully.

Finally, note that more than one parameter can be sent, you need not limit yourself.


##### POST
A new user can be inserted by sending a JSON object in the body of the HTTP request to `/api/v2/User/login`, conversely signing in is handled at the same endpoint by sending the same JSON object most of the information for this object should be derived from the Facebook graph API:

```json    
{
    "device_id" : "some_id",
    "device_type" : "1 for Apple, 2 for Android, specify an int here",
    "push_token" : "some_token",
    "facebook_id" : "xyz",
    "first_name" : "John",
    "last_name" : "Doe",
    "dob" : "Y-m-d",
    "about" : "some details", 
    "email" : "john.doe@gmail.com",
    "friends" : "facebookID1, facebookID2, facebookID3",
    "images" : "http://server.com/image1.png, http://server.com/image2.png",
    "pic_url" : "http://server.com/profilePic.png",
    "sex" : "1 for male, 2 for female.  Specify an int here", 
}
```
    
The server will process this object and determine whether or not the user should be registered or logged in.  If they are logged in then a new session is created and these details are returned in the response payload.  It should be noted that the `session_token` that is returned must be used to validate future sessions. Failure to provide this will cause the users session to be terminated.

It should also be noted that this resource will add Facebook friends each time the app is opened (synchronises with the FB server), duplicate friends will not be added, any other changes such as profile picture changes will be committed here too.

All information on the user is returned once they have been logged in or signed up:

```json
{
    "error": 200,
    "message": "You were logged in successfully, friends may have been updated: Oops, no users were added: out of 3 friends, 0 were inserted, 2 were duplicates and 1 of your friends does not have a Kinekt account.",
    "data": {
        "entity": {
            "error": "200",
            "message": "Successfully retrieved the user with id: 22033015983012329",
            "payload": {
                "Entity_Id": "1537",
                "Fb_Id": "22033015983012329",
                "First_Name": "Jason",
                "Last_Name": "Sims",
                "Email": "alexander.sims92@gmail.com",
                "Profile_Pic_Url": "https://scontent.xx.fbcdn.net/hphotos-xpa1/v/l/t1.0-9/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
                "Sex": "1",
                "DOB": "0000-00-00",
                "About": "Test bio",
                "Create_Dt": "2015-06-18",
                "Last_CheckIn_Lat": "",
                "Last_CheckIn_Long": "",
                "Last_CheckIn_Place": null,
                "Last_CheckIn_Country": null,
                "Last_CheckIn_Dt": "2015-06-18 11:53:23",
                "Score": "0",
                "Score_Flag": "0",
                "Image_Urls": "https://scontent.xx.fbcdn.net/hphotos-xfa1/v/t1.0-9/10433863_143728155797528_592834255749314929_n.jpg?oh=de519675416c138aece4392d2225242c&oe=55C3D186",
                "Category": "1"
            }
        },
        "session": {
            "Token": "44323337433946352D423445352D343034432D423246452D3943393T5cy4ObCcMYvORFvI9JM74536313238414142T5cy4ObCcMYvORFvI9JM",
            "Expiry_local": "2015-07-18 12:18:34",
            "Expiry_GMT": "2015-07-18 16:18:34",
            "Flag": 1
        }
    }
}
```

2) A users favorite places can be appended to by using `/api/v2/add-favourite/{userId}`.  The JSON payload needs the following information:

```json
{
    "picUrl" : "https://assets-cdn.github.com/images/modules/open_graph/github-mark.png",
    "placeName" : "Github HQ"
}
```

This test request will respond with a 200 if it was successful, otherwise the following message will be returned:

```json
{
    "error": 409,
    "message": "This place is already in your favourites list.",
    "data": "No data available for this resource."
}
```

##### DELETE
A user can be deleted by sending a single userId to the URI: `/api/v2/User/{userId}` - deleting a user through this interface will delete all of their posts, messages, checkins and all other information, this request must be authenticated by sending a `session_token` and `device_id` to the API. If the senderID does not match userID then the request will fail by returning a `401` code.

## Message Endpoint
All message requests are documented here, as the application grows it would be great to implement group chat - this 
feature would then require the extra URI's to be implemented...

##### GET
All requests made here require `session_token` and `device_id` to be sent.  This ensures that the user requesting the information is logged in and isnt some random server trying to gain our resources.

1) All conversations for a user can be retrieved using `/api/v2/Message/conversations/{userId}` this will retrieve all conversations for a specific user, a `200` is always returned however the response will either say no conversations were returned, or a list of all conversations for that user is sent back:

```json
{
    "error": 200,
    "message": "Successfully retrieved all conversations.",
    "data": [
        {
            "Entity_Id": "1527",
            "First_Name": "Jason",
            "Last_Name": "Sims",
            "Profile_Pic_Url": "https://scontent.xx.fbcdn.net/hphotos-xpa1/v/l/t1.0-9/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
            "mid": "2361",
            "message": "test test test test test test test test test test test",
            "msg_dt": "2015-06-16 15:50:35"
        },
        {
            "Entity_Id": "392",
            "First_Name": "Brodie",
            "Last_Name": "Dickson",
            "Profile_Pic_Url": "https://fbcdn-sphotos-b-a.akamaihd.net/hphotos-ak-xap1/v/t1.0-9/1521934_630954580305605_1902843116_n.jpg?oh=6f9c33ba3a17c869dfcb22fc07290676&oe=560B3030&__gda__=1438935833_70987c5fa430f351c2eddb312614cffc",
            "mid": "2358",
            "message": "Hey how Ìs it goin?",
            "msg_dt": "2015-05-09 14:08:44"
        }
    ]
}
```

OR

```json
{
    "error": 200,
    "message": "Query has returned with no conversations found. Either the two users are not friends, or no messages have been sent.",
    "data": []
}
```

2) All messages for a conversation can be retrieved using `/api/v2/Message/messages/{friendId}` where `{friendId}` is the conversation id.  This will return a list of all messages in that conversation ordered by date, from newest to oldest. As with the previous resource, `200` is always returned.

```json
{
    "error": 200,
    "message": "Query has executed successfully, with no results found.",
    "data": "No data available for this resource."
}
```

The above occurs when somebody with the incorrect session_token and device_id try and get the contents of a chat between two users that does not include them.

```json
{
    "error": 200,
    "message": "Successfully retrieved messages.",
    "data": [
        {
            "mid": "2381",
            "sender": "1517",
            "receiver": "392",
            "Profile_Pic_Url": "https://fbcdn-sphotos-b-a.akamaihd.net/hphotos-ak-xap1/v/t1.0-9/1521934_630954580305605_1902843116_n.jpg?oh=6f9c33ba3a17c869dfcb22fc07290676&oe=560B3030&__gda__=1438935833_70987c5fa430f351c2eddb312614cffc",
            "First_Name": "Brodie",
            "Last_Name": "Dickson",
            "message": "test",
            "msg_dt": "2015-06-17 00:00:00"
        },
        {
            "mid": "2382",
            "sender": "392",
            "receiver": "1517",
            "Profile_Pic_Url": "https://scontent.xx.fbcdn.net/hphotos-xpa1/v/l/t1.0-9/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
            "First_Name": "Jasosn",
            "Last_Name": "Sims",
            "message": "test 2",
            "msg_dt": "2015-06-30 00:00:00"
        }
    ]
}
```

##### POST
1) Send a new message to a friend using `/api/v2/Message/send-message/{friendId}`. The json object sent requires the following information:

```json
{
    "message" : "some message"
}
```

The response: 

```json
{
    "error": 200,
    "message": "Message has been sent successfully.",
    "data": "No data available for this resource."
}
```

If request was invalid a `401` message is returned meaning the user is not authorised.  Otherwise the message is attempted to be sent.  If everything was successful a `200` is returned else a `400` is returned.

2) Send a report email to the checkmates server by using `/api/v2/Message/report-email/{userId}`. Nothing else is needed for this resource as the `session_token` and `device_id` are sent in the header of every request.

In conjunction with this, an error `200` is returned if the email was sent successfully to our mailbox server.  Else an error `400` is returned, meaning it failed.

##### DELETE
1) Delete a single message inside of a message thread `/api/v2/Message/delete-message/{messageId}` simply supply the ID of the message that needs to be deleted.  No JSON body is required. `400` is returned if the conversation couldnt be deleted, else `200` was returned.  A 400 will always be returned if you attempt to DELETE a message that does not belong to you.

2) Delete a whole conversation between two people `/api/v2/Message/delete-conversation/{friendId}` supply the ID of the friend you are talking to, this will delete the entire conversation between two parties. No JSON body is required.  `400` is returned if the conversation couldnt be deleted, else `200` was returned.


## Checkin Endpoint
Resource list for manipulating checkins within the system.

##### GET
1) Retrieve all checkins for a specific user: `/api/v2/Checkin/{userId}` no other information is needed with this as the tokens are sent in the header.

2) Retrieve all checkins around a certain location: `/api/v2/Checkin/around-location/{long}/{lat}`. In order to use this resource a `session_token` and `device_id` must be sent in the header of the HTTP request, this is because GET request cannot receive a JSON payload.

Example request: `/api/v2/Checkin/around-location/51.5099/-0.133541` 

Example response:

```json
{
    "error": 200,
    "message": "Successfully retrieved 2 users around your location!",
    "data": {
        "users": [
            {
                "Entity_Id": "1517",
                "Profile_Pic_Url": "https://scontent.xx.fbcdn.net/hphotos-xpa1/v/l/t1.0-9/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
                "Last_CheckIn_Lat": "51.5099",
                "Last_CheckIn_Long": "-0.133541",
                "first_name": "Jasosn",
                "last_name": "",
                "place_name": "McKinsey & Co",
                "place_lat": "51.5099",
                "place_long": "-0.133541",
                "place_people": "505",
                "checkin_photo": "./public/img/checkins/c1776747165884740.jpg",
                "checkin_comments": "0",
                "checkin_likes": "0",
                "distance": "7493.854890264675",
                "FC": "3",
                "date": "2015-06-17 12:15:40"
            },
            {
                "Entity_Id": "1535",
                "Profile_Pic_Url": "https://fbcdn-sphotos-g-a.akamaihd.net/hphotos-ak-xfp1/v/t1.0-9/p180x540/10364032_1450745385243813_2041085208857430323_n.jpg?oh=0f8971a5ba744ef6b2edafabce813e58&oe=5627CB8B&__gda__=1441985370_938410e9d763391fca77659f2d640fb3",
                "Last_CheckIn_Lat": "51.5099",
                "Last_CheckIn_Long": "-0.133541",
                "first_name": "Davis",
                "last_name": "",
                "place_name": "McKinsey & Co",
                "place_lat": "51.5099",
                "place_long": "-0.133541",
                "place_people": "505",
                "checkin_photo": "",
                "checkin_comments": "0",
                "checkin_likes": "0",
                "distance": "7493.854890264675",
                "FC": "3",
                "date": "2015-06-17 12:16:45"
            }
        ]
    }
}
```

This should provide you with all information necessary to build the map UI on the front end. 

3) Get all Activities for your friends at `/api/v2/Checkin/activities/{userId}` no JSON body is required, just send auth data in the HTTP header.


##### PUT
Like a checkin, by specifying `like` we can filter what happens with the resource: `/api/v2/Checkin/like-checkin/{checkinId}`

##### POST
Post a new comment on a checkin by specifying the `comment` filter in the URI: `/api/v2/Checkin/add-comment/{checkinId}`

Create a new checkin, send the checkin information in the body of the HTTP request: `/api/v2/Checkin` **NOTE** Images and form data must be sent as `multipart/form-data` for this request, failure to do so will result in a 400 being returned, session_token and device_id must also be provided in the header as usual.

The multi-part/form-data object must contain the following values:

```json
{
    "image" : "path to image on your file system (T_FILE)",
    "place_name" : "some_name (T_STRING)",
    "place_lat" : "some_lat (T_STRING)",
    "place_long" : "some_long (T_STRING)",
    "place_country" : "some_country (T_STRING)",
    "place_category" : "some category i.e. office, this derives an image from the maps API (T_STRING)",
    "cat_id" : "The id of the category used, derived from maps API (T_STRING)",
    "place_people" : "number of people who have checked in here, derived from maps API (T_STRING)",
    "place_url" : "URL to the badge image for this place, derived from maps API (T_STRING)",
    "message" : "The message associated with this checkin (T_STRING)",
    "tagged_users" : "An array of user ids who are tagged in this checkin in the format: [id1, id2, id3] (T_STRING)"
}
```

This will return the newly created checkin along with other details in the payload such as the checkins history and details about how many push notifications were returned.  `5xx` errors will be returned if error messages returned by nested queries in the operation do not return an error in the `2xx` or `4xx` range, this should never happen and displays a complete system failure.   Other than that expect `200` when the request is successful but not all push notifications were sent, or `203` on a completely successful insert and notification round.  Expect `401` for unauthorised and `400` for a bad request. 

Example Response:

```json
{  
   "error":400,
   "message":"The Checkin was created successfully.",
   "data":{  
      "details":{  
         ":entityId":"1517",
         ":placeName":"McKinsey & Co",
         ":placeLat":"51.5099",
         ":placeLong":"-0.133541",
         ":placeCountry":"United Kingdom",
         ":placeCategory":"Office",
         ":placePeople":"505",
         ":message":"Hello this is my message.",
         ":taggedIds":"[1, 222, 2]",
         ":chkDt":"2015-06-19 07:30:08",
         ":placePicUrl":"146.185.147.142/checkmates/public/img/4bf58dd8d48988d124941735.png",
         ":imageUrl":"./public/img/checkins/c1210212912303096.png"
      },
      "tagged_pushes":"0/1 pushes were sent to tagged user recipients",
      "friend_pushes":"2/3 pushes were sent to friends who wish to receive notifications.",
      "people":{  
         "error":"200",
         "message":"Successfully retrieved 2 users at this location.",
         "payload":[  
            {  
               "Entity_Id":"1535",
               "first_name":"Davis",
               "last_name":"Allie",
               "profilePic":"https:\/\/scontent.xx.fbcdn.net\/hphotos-xfp1\/v\/t1.0-9\/p180x540\/10364032_1450745385243813_2041085208857430323_n.jpg?oh=0f8971a5ba744ef6b2edafabce813e58&oe=5627CB8B",
               "latitude":"51.5099",
               "longitude":"-0.133541",
               "placeName":"McKinsey & Co"
            },
            {  
               "Entity_Id":"1512",
               "first_name":"Jason",
               "last_name":"Sims",
               "profilePic":"https:\/\/scontent.xx.fbcdn.net\/hphotos-xpa1\/v\/l\/t1.0-9\/10858622_261291000707909_6269576909225534150_n.jpg?oh=761ff74185c308a1e2afad8154dc880d&oe=55C50924",
               "latitude":"51.5099",
               "longitude":"-0.133541",
               "placeName":"McKinsey & Co"
            }
         ]
      }
   }
}
```

##### DELETE
Delete a checkin: `/api/v2/Checkin/{checkinId}`


##Friend Endpoint
All endpoints for a Friend request are detailed here.

##### GET
1) All friends of a specific user can be retrieved using `/api/v2/Friend/{userId}` this method will respond with `404` if no friends were found for this user, else it will respond with `200` meaning users were retrieved successfully.

2) All friend requests for a specific user can be retrieved using `/api/v2/Friend/friend-requests/{userId}` this method should always respond with a `200` however the size of the returned payload may vary.

3) Suggested friends can now be retrived at `/api/v2/Friend/suggested-friends/{userId}`

##### PUT
1) A user can be blocked by sending a request to `/api/v2/block` a JSON object must also be sent which contains the ID of the logged in user 

```json
{
    "entity_id" : "some_id"
}
```

This resource will return `200` if the user was blocked successfully, otherwise a `409` message will be returned, menaing the relationship does not exist.  If no id is provided for the entity then a `422` message is returned, this means that auth info was not set in the HTTP header.


##### POST
1) A friend request can be sent to another user using `/api/v2/Friend/send-request/{friendId}` along with this a JSON object must be sent:

```json
{
    "session_token" : "some_token",
    "device_id" : "some_id",
}
```

If the request was accepted successfully a `200` code will be returned, otherwise a `400` will be returned.

2) A friend request can be accepted using `/api/v2/Friend/accept-request/{friendId}` along with this a JSON object must be sent:

```json
{
    "session_token" : "some_token",
    "device_id" : "some_id",
}
```

If the request was accepted successfully a `200` code will be returned, otherwise a `400` will be returned.


##### DELETE
1) A friend can be removed by sending a request to: `/api/v2/Friend/remove-friend/{friendId}`, accompanying this the JSON payload should contain the following data:

```json
{
    "session_token" : "some_token",
    "device_id" : "some_id",
}
```

This payload is used to authenticate the user.  If an invalid token and device pair are  provied a `401` error will be returned.  A `200` is returned if the request was successful, otherwise a `409` is returned detailing that the relationship doesn't exist.

2) A friend request can be rejected by sending a request to: `/api/v2/Friend/reject-request/{friendId}`, accompanying this the JSON payload should contain the following data:

```json
{
    "session_token" : "some_token",
    "device_id" : "some_id",
}
```

This payload is used to authenticate the user.  If an invalid token and device pair are  provied a `401` error will be returned.  A `200` is returned if the request was successful, otherwise a `400` is returned detailing that the friend request does not exist.



Feel free to keep adding to this as you please guys :) 

--Alex.

