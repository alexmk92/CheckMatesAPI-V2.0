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
* All users can be retrieved via`/api/v2/User`
* A specific user can be retrieved via `/api/v2/User/{userId}`, in extension to this, a list of user Id's can
be sent to retrieve multiple users.
* Users can be retrieved within a radius around a location by using GET via `/api/v2/User/at-location/{long}/{lat}/{radius}/{limit}`, it should
be noted that `{limit}` is an optional parameter, if this is not specified a default of 50 users will be returned.
* All friends of a specific user can be retrieved by: `/api/v2/User/friends/{userId}`

##### PUT
* A single user can be updated by sending a new JSON object with the new information to `/api/v2/User/{userId}`. Please note that
the JSON object sent must contain all information on the given user.
* A users position can be updated by specifying the new long/lat in the URI via: `/api/v2/User/{userId}/{long}/{lat}`.

##### POST
* A new user can be inserted by sending a JSON object in the body of the HTTP request to `/api/v2/User`
* A new friend can be added by sending a userId in the body of the HTTP reques to `/api/v2/User/friends/{userId}`, this will 
send a new friend request to the specified user.

##### DELETE
* A user can be deleted by sending a single or multiple userId's to the URI: `/api/v2/User/{userId}` - deleting a user through
this interface will delete all of their posts, messages, checkins and all other information.
* Friends can be deleted by sending the friend ID to: `/api/v2/User/friends/{userId}`

## Message Endpoint
All message requests are documented here, as the application grows it would be great to implement group chat - this 
feature would then require the extra URI's to be implemented...

##### GET
* Messages sent by a user: `/api/v2/Message/sent-by/{userId}`
* Messages sent to a user: `/api/v2/Message/sent-to/{userId}`
* Threads a user belongs to: `/api/v2/Message/thread/{userId}`

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
* Create a new checkin, send the checkin information in the body of the HTTP request: `/api/v2/Checkin`

##### DELETE
* Delete a checkin: `/api/v2/Checkin/{checkinId}`

Feel free to keep adding to this as you please guys :) 

--Alex.

