# Controllers

Controllers handle requests.
They define how to extract relevant information from the
request, check permissions by calling the correct [`Permission`](php-transactions#permissions) and calling the suitable [transaction](php-transactions).
They define which HTTP response including the response code
is sent back and the conversion of internal data to json
(`return $this->handleView(...)`).

Since the business logic („What is part of an event (= transaction)?“)
is in the transaction classes, a controller method
usually just calls one actual transaction method (apart from permission checks).
It can read necessary information from the session to give those
as arguments to the transaction class.

We have:
- [REST controllers](../../../deployment/requests#rest-api) with the name `<submodule>RestController.php`
- render controllers with the name `<module>Controller.php`

Render controllers are called that because they always render a part of the website,
as opposed to API controllers (like REST),
which are usually called by the rendered website (client) and return data, not an HTML document.
