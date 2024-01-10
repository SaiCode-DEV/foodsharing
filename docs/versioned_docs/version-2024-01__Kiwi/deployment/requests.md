# HTTP Request

The traditional loading of a page is a `http` request,
e.g. calling the main address `https://foodsharing.de` calls `/src/Entrypoint/IndexController.php`
which uses other `php` files to answer the request.
The `php` builds `html`, `css` and `javascript` and sends them to the client.

Other ways to interact with the foodsharing platform are:
- [(Legacy) XHR](#xhr) - do not use in new code!
- [the REST API endpoints](#rest-api) - the preferred option
- [our chatserver](#nodejs-for-messages)


## XHR

XHR ([XMLHttpRequest](https://en.wikipedia.org/wiki/XMLHttpRequest)) is used throughout the project for historic reasons, but should be replaced with modern API endpoints where possible.
So do not implement new features with XHR! The following is just documentation to understand what exists :)

We used XHR for information transferred from the server to the client which is not a complete new page but javascript-initiated.
For example, the Update-Übersicht on the Dashboard was loaded by an XHR that gets a json file with the information of the updates.
The javascript was found in `/client/src/activity.js`, and
it called XHR endpoints like `http://foodsharing.de/xhrapp?app=basket&m=infobar`.

This requests an answer by `/src/Entrypoint/XhrAppController.php` which in turn calls the correct `php` file based on the options that are given after the `?` in the url.
For example, the `activity.js` requests were answered by
`/src/Modules/Activity/ActivityXhr.php`.
In this example, the database was queried for information via `ActivityModel.php` which in turn used the `/src/Modules/Activity/ActivityGateway.php`.

There are a two mostly identical XHR endpoints - `/xhr` and `/xhrapp`. Nowadays, those are handled by `XhrController.php` and `XhrAppController.php` respectively.

XHR-request answers contain a status and data and <!-- todo --> ? and always sends the HTTP status 200.
So errors are not recognizable by the HTTP status, but by a custom status in the returned json response.


## REST API

The more modern way to build our api is a [REST api](https://symfony.com/doc/master/bundles/FOSRestBundle/index.html) by FOS (friends of symfony).
The documentation of the REST api endpoints is located at the definition of the endpoints and can be nicely viewed on (https://beta.foodsharing.de/api/doc/).

In the [documentation](https://symfony.com/doc/current/bundles/NelmioApiDocBundle/index.html) you can read how to properly include the documentation.
A good example can be found in `/src/RestApi/ForumRestController.php`.
<!-- TODO: how is this created? -->

In the [Code quality page](code-review) we have some notes on how to define the REST API Endpoints.

The javascript code that sends REST API requests is found under `/client/src/api` and is used by other javascript by [import](../frontend/javascript).

All php classes working with REST requests are found in [`/src/Modules/RestApi/<..>RestController.php`](https://symfony.com/doc/current/controller.html).
This is configured in [`/config/routes/api.yml`](https://symfony.com/doc/current/bundles/FOSRestBundle/5-automatic-route-generation_single-restful-controller.html).
There it is also configured, that calls to `/api/` are interpreted by the REST api, e.g.
```
https://foodsharing.de/api/conversations/<conversationid>
```
This is being called when you click on a conversation on the „Alle Nachrichten“ page.

REST is configured via [annotations](https://symfony.com/doc/master/bundles/FOSRestBundle/annotations-reference.html) in comments in functions.
  - `@Rest\Get("subsite")` specifies the address to access to start this Action: `https://foodsharing.de/api/subsite"
  - `@Rest\QueryParam(name="optionname")` specifies which options can be used. These are found behind the `?` in the url: `http://foodsharing.de/api/conversations/687484?messagesLimit=1` only sends one message.
  - Both `Get` and `QueryParam` can enforce limitations on the sent data with `requirement="<some regular expression>"`.
  - `@SWG\Parameter`, `@SWG\Response`, ... create the [documentation](https://symfony.com/doc/current/bundles/NelmioApiDocBundle/index.html) (see above)

Functions need to have special names for symfony to use them: the end with `Action`.
They start with a permission check, throw a `HttpException(401)` if the action is not permitted.
Then they somehow react to the request, usually with a Database query via the appropriate Model or Gateway classes.

During running php, the comments get translated to convoluted php code.
REST also takes care of the translation from php data structures to json.
This json contains data. Errors use the [error codes of http-requests](https://en.wikipedia.org/wiki/List_of_HTTP_status_codes).

While reading and writing code a (basic) [manual](https://symfony.com/doc/master/bundles/FOSRestBundle/index.html)
and an [annotation overview](https://symfony.com/doc/master/bundles/FOSRestBundle/annotations-reference.html) will help.


### Handling of big request body

The foodsharing plattform uses sometimes big request bodies (e.g  create/update Store, create and/or update region/working groups).

For a better OpenAPI documentation and to reduce boring repeating (often error prone) conversion code or validation code the following parts can be used.

#### Introduction

The implementation of an RestAPI endpoint action typically starts with a RestAPI description with annotations for (Swagger-php)[http://zircote.github.io/swagger-php/] integrated by (Symfony via NelmioApiDocBundle)[https://symfony.com/bundles/NelmioApiDocBundle/current/index.html] followed by FOSBundle endpoint type description.

A typical endpoint follows the following steps.

~~~plantuml
@startuml
Client -> EndpointAction: "POST create /api/store with request body"
EndpointAction -> Request: "read content"
Request --> EndpointAction
EndpointAction -> EndpointAction: "Validate content"
EndpointAction --> EndpointAction: "no errors"

EndpointAction -> Transaction:"Run business logic"
Transaction -> Gateway: "Get data from database"
Gateway --> Transaction: "A value"
Transaction -> Gateway: "Store new data to database"
Transaction --> EndpointAction: "Business logic execution complete"
EndpointAction --> Client: Response
@enduml
~~~

#### Request convertation

The request body contains a typical JSON object with different elements.
The extraction of this information can be done by `@RequestParam()`. For many JSON elements it is a lot of conversion code. 
With the `@ParamConverter()` the elements are converted directly into an object.

The `@ParamConverter()` requires therefor a class definition. The converter generates an object of the class and fills all fields with the information found in the request body.
The nice benefit is that the class definition member phpdoc strings are used for the OpenAPI documentation.

#### Validation

The conversion to the class does not mean that all restrictions of the values are fulfilled.
This can be checked manually or by the [symfony validation libary](https://symfony.com/doc/current/validation.html). 

The validation library uses the annotations to descibe rules for validation of the class members.
The endpoint action only need to check if error are detected and can throw an bad request error.

If the validation was succesful the business logic like an transaction can be used and the sanitation of the RestAPI RequestBody content is solved in a typical Symfony way.

## Socket.io for notifictations and chat

The chats have a separate way of communication between client and server.
For all other pages the initiative starts at the client,
the client sends a request to the (`php`) server and gets an answer.
Each request uses one connection that is closed afterwards.
This is not useful for the chats since the server knows that there are new messages and has to tell the client.

For this case there exists a separate `nodejs` server (on the same machine as the `php` server but separate). This holds an open connection to each user that has `foodsharing.de` open on their device. Each time a message arrives at the php server, it sends this information to the `nodejs` server via a websocket
> TODO: explanation what a websocket is, on which server is it, is it the right place to explain it?

which uses the connection to the client to send the message.
Note that there can be several connections to each session, of which there can be several for each user. `nodejs` sends the message to all connections of all addressed users.

The code for the `nodejs` server is found in `/chat/src/index.ts` and other files in `/chat/src`
chat/socket.io -> nodejs server, in chat/src/index.ts. There is documentation for all used parts in `/chat/node_modules/<modulename>/Readme.md`. All `nodejs`-documentation is found on [their webpage](https://nodejs.org/en/docs/).

> php server tells websocket that there is a new message

> nodejs-server sends message to all open connections of all sessions of all users



