# CheckMates API - Version 2.0
The Checkmates API is being redeisgned as a RESTful web API to allow the application interface to be tapped into 
more easily.  The previous system is riddled with many bugs which cause strange discrepancies when pulling 
information back from the web service.  

Below is a brief overview of the Endpoints (interfaces) available to use in the API, this is a very brief overview
and a full developer reference will be provided in future.

Currently all information returned from the API is in JSON, in future I may add the capability to serialise to XML for
wider support, but it is not a huge priority.

## User Endpoint
All user requests are documented here:

##### GET
* All users can be retrieved via`/api/v2/User`.  

* A specific user can be retrieved via `/api/v2/User/{userId}`.

* All friends of a specific user can be retrieved by: `/api/v2/User/friends/{userId}`

##### PUT
* A user can be updated by sending a new JSON object with the new information to `/api/v2/User/{userId}`. Please note that the JSON object sent must contain all information on the given user.  This resource does not need to be called directly as changes set here are automatically performed when the user signs up or logs in, however in the event that a user updates there `about` information, this resource must have the following fields passed in a PUT method:

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
    "
}
```

* A users position can be updated by specifying the new long/lat in the URI via: `/api/v2/User/update-location/{lat}/{long}` in addition to the longitude and latitude sent in the URL, a JSON object containing session information must be provided.

```json
{
    "device_id" : "some_id",
    "session_token" : "some_token"
}
```

If this information is not correct then a 401 will be returned. An error 500 may also be returned if the resource does not update, this normally means that the Database could not be reached.

##### POST
* A new user can be inserted by sending a JSON object in the body of the HTTP request to `/api/v2/User`, conversely signing in is handled at the same endpoint by sending the same JSON object most of the information for this object should be derived from the Facebook graph API:

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
    "
}
```
    
The server will process this object and determine whether or not the user should be registered or logged in.  If they are logged in then a new session is created and these details are returned in the response payload.  It should be noted that the `session_token` that is returned must be used to validate future sessions. Failure to provide this will cause the users session to be terminated.

It should also be noted that this resource will add Facebook friends each time the app is opened (synchronises with the FB server), duplicate friends will not be added, any other changes such as profile picture changes will be committed here too.

##### DELETE
* A user can be deleted by sending a single userId to the URI: `/api/v2/User/{userId}` - deleting a user through this interface will delete all of their posts, messages, checkins and all other information, this request must be authenticated by sending a `session_token` and `device_id` to the API. If the senderID does not match userID then the request will fail by returning a `401` code.

```json
{
    "device_id" : "some_id",
    "session_token" : "some_token"
}
```

## Message Endpoint
All message requests are documented here, as the application grows it would be great to implement group chat - this 
feature would then require the extra URI's to be implemented...

##### GET
* 

##### POST
* Send a new message to a specific message thread.  The message is sent in the body of the HTTP request: `/api/v2/Message/thread/{threadId}`

##### DELETE
* Delete a chat thread: `/api/v2/Message/thread/{threadId}`
* Delete a specific message: `/api/v2/Message/{messageId}`

## Checkin Endpoint
* Resource list for manipulating checkins within the system.

##### GET
* Retrieve all checkins for a specific user: `/api/v2/Checkin/{userId}`
* Retrieve all checkins around a certain location: `/api/v2/Checkin/{long}/{lat}/{radius}`

##### PUT
* Like a checkin, by specifying `like` we can filter what happens with the resource: `/api/v2/Checkin/like/{checkinId}`

##### POST
* Post a new comment on a checkin by specifying the `comment` filter in the URI: `/api/v2/Checkin/comment/{checkinId}`
* Create a new checkin, send the checkin information in the body of the HTTP request: `/api/v2/Checkin` **NOTE** Images and form data must be sent as `multipart/form-data` for this request, failure to do so will result in a 400 being returned.

##### DELETE
* Delete a checkin: `/api/v2/Checkin/{checkinId}`

Feel free to keep adding to this as you please guys :) 

--Alex.

