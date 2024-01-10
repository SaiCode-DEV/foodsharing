# Usage for implementation

The implementation of an RestAPI endpoint action typically starts with a RestAPI description with annotations for [Swagger-php](http://zircote.github.io/swagger-php/) integrated by [Symfony via NelmioApiDocBundle](https://symfony.com/bundles/NelmioApiDocBundle/current/index.html) followed by FOSBundle endpoint type description.

Following code shows an example of an endpoint

~~~php
/**
 * <Endpoint description>
 *
 * @OA\Tag(name="<Module>")
 *
 * @Rest\Post("ressources/{:ressourceId}/subRessource")
 * @OA\RequestBody(@Model(type=RequestBodyClassRepresention::class))
 * @ParamConverter("requestDeserializedContent", converter="fos_rest.request_body")
 *
 * @OA\Response(response=Response::HTTP_CREATED,
 *	description="<Description of the response for a http status code>",
 *  @Model(type=ResponseRepresentation::class)
 * )
 * @OA\Response(response=Response::HTTP_BAD_REQUEST, description="<Description of the response for a http status code>")
 * @OA\Response(response=Response::HTTP_UNAUTHORIZED, description="<Description of the response for a http status code>")
 */
public function endpointAction(int $ressourceId, RequestBodyClassRepresention $requestDeserializedContent, ValidatorInterface $validator): Response
{
    // Access permission check
    if (!$this->session->mayRole()) {
        throw new UnauthorizedHttpException('', self::NOT_LOGGED_IN);
    }

    if (!$this->permissions->myCanAccess($regionId)) {
        throw new AccessDeniedHttpException('No permission to create store for this region');
    }

    // Validation of content
    $errors = $validator->validate($storeCreateInformation);
    if ($errors->count() > 0) {
        $firstError = $errors->get(0);
        $relevantErrorContent = ['field' => $firstError->getPropertyPath(), 'message' => $firstError->getMessage()];
        throw new BadRequestHttpException(json_encode($relevantErrorContent));
    }

    // Prepare and call transaction for execution of business logic
    $storeModel = new MinimalStoreModel();
    $store = $storeCreateInformation->store->toCreateStore();
    $store->regionId = $regionId;
    $storeModel->id = $this->storeTransactions->createStore($store, $this->session->id(), $storeCreateInformation->firstPost);

    // generate response
    return $this->handleView($this->view($storeModel, Response::HTTP_CREATED));
}
~~~


A typical endpoint follows the following steps.

~~~plantuml
@startuml
Client -> EndpointAction: "POST create /api/ressources/1/subRessource with request body (Content)"
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

## Common

All php classes working with REST requests are found in [`/src/Modules/RestApi/<..>RestController.php`](https://symfony.com/doc/current/controller.html).
Functions need to have special names for symfony to use them: the end with `Action`.

The generic `HttpException` class using a status code but a specific subclass instead. 
For example, use `BadRequestHttpException('something went wrong')` instead of `HttpException(400, 'something went wrong')`. 

They start with a permission check, throw a `HttpException(401)` if the action is not permitted.
Then they somehow react to the request, usually with by transactions or sometimes gateways.

## Sanitization

Is done by symfony via  `@ParamConverter("requestDeserializedContent", converter="fos_rest.request_body")`. 
It use jmserializer to convert it to the class, in some cases it is required to define the type of the properties with more details like for [arrays](https://gitlab.com/foodsharing-dev/foodsharing/-/merge_requests/2515/diffs#50bfc7418e3eddef50cf90b4f240ee833a9f509f_0_196).

- Enum´s are currently not automatic converted, we use integers and describe the values in the documentation.

## Permission check

-  use *Permission* classes for permission checks

[Existing implementation](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/Permissions)

## Validation

- Validate content for logic or used by permission
- Invalid content should return an badRequest with some information.

[More information](https://symfony.com/doc/current/validation.html)
[Existing implementation](https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/src/Validator)


## Business logic

The endpoint with its HTTP methodes execute actions on the data in the foodsharing platform.
This business logic is implemented by [transactions](../php/php-transactions.md) or [gateway](../php/php-gateways.md). The Endpoint should only use, so that business logic is in independent from the
representation.

## Others

- For the response, use DTOs that will be transformed to JSON by Symfony and the description in the class is used by swagger-php for documentation, The validation roles can 
  be described for validation on the properties.


More not-yet-implemented ideas include:
1. Add API versioning (to allow introducing breaking api changes in the future without immediately breaking the apps) ([not yet](https://gitlab.com/foodsharing-dev/foodsharing/issues/511#note_173339753), hopefully coming at some point)
1. Standardize pagination (e.g. fixed query param names, return total number of items, either via envelope or header)
