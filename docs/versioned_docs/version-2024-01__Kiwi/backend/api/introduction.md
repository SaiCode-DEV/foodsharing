# Introduction

The RestAPI is the major communication interface between backend and frontends like web client, or native apps. The RestAPI is described using [OpenAPI](https://swagger.io/docs/specification) and rendered by swagger-php. That way developers can see all available API endpoints documented nicely.

The foodsharing documentation is provided on
- [Devdocs](https://devdocs.foodsharing.network/docs-api/foodsharing-api)
- [OpenAPI/Swagger for Beta ](https://beta.foodsharing.de/api/doc/)
- [OpenAPI/Swagger for Production ](https://foodsharing.de/api/doc/)
- [API Documentation (local development environment)](http://localhost:18080/api/doc/)


This page describes the basics, design patterns and constraints for a consistent and understandable RestAPI.

## Basic structure

The foodsharing platform provide all information via RestAPIs. The RestApi is implemented on endpoint and should follow the belower concept.

### Endpoints

Every [endpoint](https://swagger.io/docs/specification/api-host-and-base-path/) is represented by the URI (endpoint path/ressource) and can support creating, reading, updating and deleting the information via HTTP methods.

Each endpoint is accessible by a endpoint path.

The following table shows some examples.

| Endpoint path (URI) | HTTP method | endpoint meaning |
|-----|--------|---------|
| /stores | GET | Return a list of stores |
| /stores/1 | GET | Get the specific store with id 1  |
| /stores | POST | Create a new store with information from request |

#### Endpoint paths / Routing

The naming of the path should represent a ressource (no actions/verbs) and can contain url parameter like `:storeId` or `:postId` that get replaced by values when accessing the path.

For example: 
- Stores are accessible via the path `/stores`
- Pickups are accessible via the path `/pickups`
- Store team is accessible via `/stores/:storeId/members`, or `/stores/123/members` for instance
- Wall posts of a store are accessible via `/stores/:storeId/posts`
- A specific wall post is accessible via `/stores/:storeId/posts/:postId`


The endpoint path can contain url parameter like `:storeId` or `:postId`.

#### HTTP methods

The endpoint represented ressource can be accessed by different HTTP methods.

| Method | meaning                                  |
|--------|------------------------------------------|
| GET    | Returns ressource information without modifications in the platform   |
| POST   | Creates a new ressource item             |
| PUT    | Replaces the information of a ressource  |
| PATCH  | Modifies the Information of a ressource |
| DELETE | Removes a ressource                      |

#### HTTP response status codes

The [HTTP response status codes](https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html) provides short information after request on an endpoint. This information allows the client to react on problems or successful response. 

Please use the following status codes consistently in REST responses. More detailled explanations are listed at https://restfulapi.net/http-status-codes/.

**Valid request:**
- 200: The request was valid and contains a response body.
- 201: The request was valid and an new ressource is created, it returns at least the Id of the new ressource.
- 204: The request was valid but does not contain a response body. This indicates that the client is not required to update the frontend.

**HTTP client authentification/access status error:**
- 401: The user is not logged in. This should indicate to redirect the client to a login page.
- 403: The user is logged in, but is not allowed to do the particular action. This should trigger an error message in the client.
 
**HTTP client request error:**
- 400: Invalid request. This means that required parameters are missing, not correctly formatted, or invalid values like out of a range.
- 404: All parameters are valid but the resource does not exist.
- 409: There is a conflict between the request parameter related state and the server stored state.  

For example, requesting `/api/user/{id}` should return a 
`400` if the `id` is not a number. It should return `404` if `id` is a number but the user with that `id` does not exist.

Other status codes can be used but should be well documented in that function. The REST endpoints should never return 5xx codes deliberately. Those codes are use to indicate critical internal errors, like syntax errors or database problems in the backend.

#### HTTP request and response body

In the foodsharing RestAPI the requests and response carry content as [JSON](https://www.json.org/json-de.html). 

The JSON objects should use following rules
- keys are always in english and use **camelCase** (`regionId` instead of `region_id`)


*Requests*

```
{
  "name": "betrieb_Lemke Hempel GmbH",
  "regionId": 1566,
  "location": {
    "lat": 49.143446,
    "lon": 9.139265
  },
  "address": {
    "street": "Angelika-Groß-Platz 71c",
    "city": "Weinstadt",
    "zip": "96694",
  },
  "isPublic": true
}
```

Object field names (keys):
- **english** 
- **camelCase** for keys 
- **prefixes** for booleans e.g. `isPublic` instead of `public`
- name keys always as specific as possible and insert the use of the field (`createdAt` instead of `time`,  `author` instead of `user`)
- Type formats

  | type | format | description |
  |--------|-------|--------------------|
  | integer | [0-9]+ | Should also be sent as an integer or boolean, not as a string |
  | boolean | [true\|false] | Should also be sent as an integer or boolean, not as a string |
  | datetime | [ISO 8601](https://en.wikipedia.org/wiki/ISO_8601) `DATE_ATOM` | |


*Response*

The response needs to follow the same structure like requests.

```
{
    "id": 1,
    "name": "betrieb_Lemke Hempel GmbH",
    "region": {
        "id": 1566,
        "name": "Göttingen"
    },
    "location": {
        "lat": 49.143446,
        "lon": 9.139265
    },
    "street": "Angelika-Groß-Platz 71c",
    "city": "Weinstadt",
    "zip": "96694",
    "cooperationStatus": {
        "name": "COOPERATION_ESTABLISHED",
        "value": 5
    },
    "createdAt": "1976-04-30"
}
```

#### HTTP query parameter

Query parameters are parameters that can be added to the endpoints for further specification of the response, like filtering, sorting or for pagination.

- filters - Parameter to filter elements from a list
- sorting - Parameter to change order of the items in a list
- expand - Parameter to expand information which are in the minimal represented by a ID.
  The `expand=1` URL query parameter for example is used to expand a list of IDs to the complete objects.
- pagination - Parameter to request a segment from a list
  The start index of items is the list is defined by `offset=d+` and the count of items after the offset is defined by
  `limit=d+`.

## References

This page base on following references

- [Microsoft](https://learn.microsoft.com/en-us/azure/architecture/best-practices/api-design)
- [Stack overflow](https://stackoverflow.blog/2020/03/02/best-practices-for-rest-api-design/)
- [Swagger/OpenAPI specification](https://swagger.io/docs/specification/api-host-and-base-path/)
