# REST API Implementation
This page is about how to properly implement REST API endpoints for foodsharing.

## Basic Implementation
All php classes controlling API requests are found in [`/src/RestApi/<..>RestController.php`](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/RestApi).

The following code shows an example of a `RestController`:

::: code-group
```php :line-numbers [ExampleRestController.php]
<?php
declare(strict_types=1);
namespace Foodsharing\RestApi;

use Foodsharing\Lib\Session;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'example')]
#[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
#[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Not permitted')]
class ExampleRestController extends AbstractFoodsharingRestController
{
    public function __construct(
        protected Session $session,
        private readonly ExampleGateway $exampleGateway,
        private readonly ExamplePermissions $examplePermissions,
        private readonly ExampleTransactions $exampleTransactions,
    ) {
    }

    #[OA\Get(
        summary: 'Get some example data',
        description: 'Optionally some additional information on the function'
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Success',
        content: new Model(type: Example::class)
    )]
    #[Route(
        'examples/{exampleId}',
        methods: ['GET'],
        requirements: ['exampleId' => Requirement::POSITIVE_INT]
    )]
    public function getExample(int $exampleId): Response
    {
        $this->assertLoggedIn();

        if (!$this->exampleGateway->exampleExists($exampleId)) {
            throw new NotFoundHttpException('Example does not exist');
        }

        if (!$this->examplePermissions->mayAccessExample($exampleId)) {
            throw new AccessDeniedHttpException('Not permitted');
        }

        $regions = $this->exampleGateway->getExample($exampleId);

        return $this->respondOK($regions);
    }
}
```
```php :line-numbers [DTO\Example.php]
<?php
declare(strict_types=1);
namespace Foodsharing\Modules\Example\DTO;

use DateTime;

class Example
{
    public string $name;
    public bool $isPublic;
    public ?int $regionId;
    public DateTime $createdAt;
    public OtherDTO $someOtherObject;

    /** @var OtherDTO[] */
    public array $moreObjects;
}
```
:::
More information on general Controllers in the [Symfony Docs](https://symfony.com/doc/current/controller.html).

## Routing
The `Route` attribute is used to define the endpoint a function responds to.
- The first property (`path`) defines the endpoint, including URI parameters like `{exampleId}`.
- `methods` defines the [HTTP methods](/backend/api/conventions#http-methods) the function handles - usually just one.
- `requirements` is needed when using URI parameters to define using RegEx, what structure they should have.  With this, the php function can simply take the URI parameter as an (automatically typecast) argument. Common reguar expressions like `[1-9][0-9]*` for positive integers are provided in `Routing\Requirement\Requirement` and should be used where possible.

## API Documentation
Documenting the API is done via OpenApi Attributes.
- `OA\Tag` is used to group endpoints into categories in the API docs. Since all endpoints handled in one controller should be part of the same category, this attribute is added to the class instead of each single function.
- `OA\Get`, `OA\Post`, `OA\Patch` and the like are used to document the API endpoints.
    - `summary` property for a concise description of the endpoint.
    - `description` property can be used for additional information that gets displayed in the details view of a API endpoint in the docs. 
- `OA\Response` is used to document different [response types](/backend/api/conventions#http-response-status-codes). These can either be added to the class (applying them to every endpoint), or to single functions.
    - `response` property for the [HTTP response code](/backend/api/conventions#http-response-status-codes). Please don't use magic numbers but the constants from `HttpFoundation\Response`.
    - `description` property to describe, when this response occurs.
    - `content` property for responses with a [response body](/backend/api/conventions#http-request-and-response-body). This should be included to indicate the response data structure. Please use [DTOs](/backend/php/php-dto#data-transfer-objects) 
    ::: tip TIP: Returning arrays
    When returning an array of DTOs, you can use `OA\JsonContent` as follows:

    ```php
    content: new OA\JsonContent(
        type: 'array',
        description: 'The list of examples.',
        items: new OA\Items(ref: new Model(type: Example::class))
    )
    ```
    :::

## Using Query Parameters
[Query parameters](/backend/api/conventions#http-query-parameters) are additional, usually optional parameters that can be added to the URI after the endpoint (e.g. `/examples?language=de&version=3`). Query parameters can be defined directly in the function head using the `MapQueryParameter` Attribute:

::: code-group
```php :line-numbers=40 [ExampleRestController.php]
    public function getExample(
        int $exampleId,
        #[MapQueryParameter] ?string $language, // [!code ++]
        #[MapQueryParameter] int $version = 1, // [!code ++]
    ): Response
```
:::

In this example, both `language` and `version` are optional - `language` because it is nullable, `version` because it has a default value.
`MapQueryParameter` automatically makes sure that the provided parameter fits the given type.

::: tip
Further validation of query parameters is possible using the `options` propery:

```php
#[MapQueryParameter(options: ['min_range' => 0])] int $version
```

This way Symfony makes sure that `$version >= 0`.
:::

When query parameters are invalid, either because they are missing or invalid, Symfony automatically throws a 404 Not Found response.

::: tip
As of December 2025, `Rest\QueryParam` is still used a lot across the code base. When using `MapQueryParameter`, this isn't required for the generated API docs though and is therefor just redundant boilerplate that should be ommitted. You may use the following attribute on the function though, if you want to provide additional information:
```php
#[OA\QueryParameter(name: 'version', description: 'Some description')]
```
:::

## Using Request Body
When sending data to the backend (usually using POST, PATCH or PUT), this data shouldn't be sent as query parameters, but as a JSON request body.
The request body is defined directly in the function head using the `MapRequestPayload` Attribute:

::: code-group
```php :line-numbers=57 [ExampleRestController.php]
    #[OA\Post(summary: 'Add an example')]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[Route('examples', methods: ['POST'])]
    public function addExample(
        #[MapRequestPayload] Example $example, // [!code highlight]
    ): Response
```
:::

This way the request body is automatically typecast to the structure of the `Example` DTO and validated according to the [DTO's Annotations](#annotating-dtos).

::: tip
As of December 2025, `OA\RequestBody` is still used a lot across the code base. When using `MapRequestPayload`, this isn't required for the generated API docs though and is therefor just redundant boilerplate that should be ommitted.
:::

## Function Structure
The functions in a REST controller should all have a structure similar to this:
#### 1. Checking Permissions
Not every user is permitted to use every API endpoint all the time. These are the checks typically found at the top of the function:
- **Rate Limiter:** Some methods should be rate limited, meaning that there is a limit to how often they can be called in a given time. This applies especially to those functions, which are either computationally costly or create data on the server. A rate limiter automatically throws a 429 Too Many Requests error response if the rate limit is exceeded.
    ::: details DETAILS on rate limiters
    This is how a rate limiter is used:
    ::: code-group
    ```php :line-numbers=40 [ExampleRestController.php]
        public function getExample(
            int $exampleId,
            Request $request, // [!code ++]
            RateLimiterFactory $exampleLimiter, // [!code ++]
        ): Response {
            $this->checkRateLimit($request, $exampleLimiter); // [!code ++]
            // ...
    ```

    ```yaml :line-numbers [rate_limiter.yaml]
    when@prod: &prod
    framework:
        rate_limiter:
            example: # [!code ++]
                policy: 'token_bucket' # [!code ++]
                limit: 30 # [!code ++]
                rate: { interval: '5 second', amount: 1 } # [!code ++]
    ```
    - The rate limiter limits the number of requests *per IP address*. This means that on one Wifi network, multiple users may be rate limited together. If you want to split different requests into multiple separately rate-limited groups, you can pass a distinguishing key as a third argument, e.g. `$this->checkRateLimit($request, $exampleLimiter, $exampleId)`. With this, the endpoint would only be limited per IP *and* per `exampleId`. 
    - The correct rate limiter to use is automatically infered based on the `RateLimiterFactory`'s name. These names can be used in multiple endpoints if you want to limit different requests together.
    - The rate limiter policy we usually use is `token_bucket`. See the [Symfony Docs](https://symfony.com/doc/current/rate_limiter.html#token-bucket-rate-limiter) for details.
    :::

- **Assert logged in:** In most cases, the calling user is required to be logged in. Use `AbstractFoodsharingRestController::assertLoggedIn` to throw a 401 Unauthorized error response if the user isn't.
- **Permission checks:** Many endpoints are only allowed for certain user groups. Checking whether the current user has the required permissions should be done via a function call to a [permission](/backend/php/php-transactions#permissions) class. Don't implement permission logic directly in the REST controller. In case the user is not permitted, throw a 403 Forbidden error response. You may distinguish different missing permissions via different error messages. See [Basic Implementation](#basic-implementation) (line 48-50) for an example.
#### 2. Validation
Even though most of the input validation can (and should) be done using [`MapQueryParameter`](#using-request-body), [`MapRequestPayload`](#using-request-body) and [DTO annotations](#annotating-dtos), sometimes you need to perform additional input validations. These are the two main scenarios for that:
- **Entity existance checks:** When working with existing entities (usually referenced via ID), you need to make sure that the given ID does actually exist. In case it doesn't, a 404 Not Found error response should be thrown. See [Basic Implementation](#basic-implementation) (line 44-46) for an example.
- **Complex data structure validation**: Type declarations and DTO annotations only support simple checks on the data. Conditional or comparative checks need to be done explicitly. When such a validation fails, a 400 Bad Request error response should be thrown.
    ::: tip EXAMPLE
    Let'a assume that the description of an `Example` is required to have a minimum length only if that `$example` is public:
    ::: code-group
    ```php :line-numbers=59 [ExampleRestController.php]
        #[Route('examples', methods: ['POST'])]
        public function addExample(
            #[MapRequestPayload] Example $example,
        ): Response {
            if ($example->isPublic && str_len($example->description) < MIN_PUBLIC_DESCRIPTION_LENGTH) { // [!code ++]
                throw new BadRequestHttpException('Longer description required for public examples'); // [!code ++]
            } // [!code ++]
        }
    ```
    :::
#### 3. Function call
Now that we know the request is valid, the actual program logic can be called. It should not be implemented in the REST controller itself though. Instead, it should simply be one call to either a [gateway](/backend/php/php-gateways) or [transactions](/backend/php/php-transactions) class. See [Basic Implementation](#basic-implementation) (line 52) for an example.

#### 4. Response
Finally a 200 OK response should be returned when the function ran sucessfully. Do so by calling `AbstractFoodsharingRestController::respondOK`. What ever data you pass as the first argument will be serialized to JSON and returned as the response body.  See [Basic Implementation](#basic-implementation) (line 54) for an example.

## Annotating DTOs
For [using a request body](#using-request-body) it is necessary to annotate the used DTOs properly. That way inputs can be validated automatically and the API docs display what data structure is required for requests.

#### Input validation
For simple checks, the [symfony validation libary](https://symfony.com/doc/current/validation.html) should be used.

::: tip EXAMPLE
Let's assume the following validation rules:
- `name` is required and must be at least 3 chars long.
- `isPublic` defaults to `false`
- `regionId` is optional, but needs to be `>= 0` if set.
- `createdAt` must not be set (when the DTO is sent as a request body), as it should be set to the current time serverside.
- `someOtherObject` is required, following the validation rules of the `OtherDTO` class.
- `moreObjects` is required, but may be an empty array. Each element needs to follow the validation rules of the `OtherDTO` class.
::: code-group
```php :line-numbers=7 [DTO\Example.php]
class Example
{
    #[Assert\NotBlank] // [!code ++]
    #[Assert\Length(min: 3)] // [!code ++]
    public string $name;

    public bool $isPublic; // [!code --]
    public bool $isPublic = false; // [!code ++]

    #[Assert\PositiveOrZero] // [!code ++]
    public ?int $regionId;

    #[Assert\Blank] // [!code ++]
    public DateTime $createdAt;

    #[Assert\NotBlank] // [!code ++]
    public OtherDTO $someOtherObject;

    /** @var OtherDTO[] */ // [!code ++]
    #[Assert\NotNull] // [!code ++]
    public array $moreObjects;
}
```
:::

::: warning
When using typed arrays, it is important to use the `@var ItemType[]` annotation. Symfony needs this type information to use the correct class for parsing elements.
:::

#### Display in the API docs
The API docs include information on how the body of a request needs to be structured. In most cases this is an optional way to improve the usability of the API, by providing an example for a possible value or additional property description:

::: code-group
```php :line-numbers=7 [DTO\Example.php]
class Example
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    #[OA\Property(example: 'API example')] // [!code ++]
    public string $name;

    #[OA\Property(description: 'Public examples are great!')] // [!code ++]
    public bool $isPublic = false;
    
    //...
```
:::

::: warning
When using typed arrays, you need to provide type information explicitly using `OA\Property`:
::: code-group
```php :line-numbers=32 [DTO\Example.php]
    /** @var OtherDTO[] */
    #[Assert\NotBlank]
    #[OA\Property( // [!code ++]
        type: 'array', // [!code ++]
        items: new OA\Items(ref: new Model(type: OtherDTO::class)) // [!code ++]
    )] // [!code ++]
    public array $moreObjects;
```
:::