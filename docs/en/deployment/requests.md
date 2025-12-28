# HTTP Request

The traditional loading of a page is a `http` request,
e.g. calling the main address `https://foodsharing.de` calls `/src/Modules/Index/IndexController.php`
which uses other `php` files to answer the request.
The `php` builds `html`, `css` and `javascript` and sends them to the client.

Other ways to interact with the foodsharing platform are:
- [the REST API endpoints](#rest-api) - the preferred option
- [our chatserver](#nodejs-for-messages)


## REST API
Most of the communication between client and server happens via the [REST API](/backend/api/introduction). API calls are always initiated by the client and responded to by the server.

The javascript code that sends REST API requests is found under [`/client/src/api`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/client/src/api) and is used by other javascript by [import](/frontend/javascript#imports).

## Socket.io for notifications and chat

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



