# REST API Conventions

## Endpoint Naming
API request [URI](https://de.wikipedia.org/wiki/Uniform_Resource_Identifier)s consist of the server URL, the endpoint and optionally query parameters.

::: tip EXAMPLE
```
https://foodsharing.network/api/map/markers/stores?status=cooperating&scope=all
\_____________________________/\_________________/ \__________________________/
          server URL                endpoint             query parameters
```
:::

The naming of an endpoint represents a resource (no actions / verbs) and can contain parameter that get replaced by values when accessing the path. URIs should be build hierarchically and use entity names in plural (e.g. `stores` instead of `store`). More information on naming [here](https://restfulapi.net/resource-naming).

::: tip EXAMPLE
| Endpoint | Resource description | Example |
|----------|----------------------|---------|
| `/stores` | All stores |
| `/stores/{storeId}` | A specific store | `/stores/1234` |
| `/stores/{storeId}/invitations` | Membership invitations to a specific store | `/stores/1234/invitations` |
| `/stores/{storeId}/invitations/{userId}` | Invitation of a specific user to a specific store | `/stores/1234/invitations/56789` |
:::

## HTTP Methods
Every API endpoint can support creating, reading, updating and deleting the information via different HTTP methods:

| Method | Meaning |
|--------|---------|
| GET    | Returns resource information without modifying them |
| POST   | Creates a new resource item |
| PATCH  | Modifies the information of a resource |
| PUT    | Fully replaces the information of a resource |
| DELETE | Removes a resource |

::: tip EXAMPLE
| Method | Endpoint | Meaning |
|--------|----------|---------|
| GET | `/api/content` | Returns a list of all content entries |
| POST | `/api/content` | Adds a new content entry |
| GET | `/api/content/{contentId}` | Returns a specific content entry |
| DELETE | `/api/content/{contentId}` | Removes a specific content entry |
| PATCH | `/api/content/{contentId}` | Updates a specific content entry |
:::

## HTTP Response Status Codes
Each HTTP response has a [Status Code](https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html) that allows the client to distinguish different problems or successful responses. 
The following status codes should be used in REST responses:

| Code | Name | Meaning |
|------|------|---------|
| 200 | OK | The request was valid. Other 2xx response codes are rarely used in the code base but should be used if there is a need to distinguish between 200 and other successfull response types. |
| 400 | Bad Request | Invalid request. Required parameters are missing, not correctly formatted, or invalid e.g. out of a range. |
| 401 | Unauthorized | The user is not logged in. In most cases the client should redirect to a login page. |
| 403 | Forbidden | The user is logged in, but not permitted to do the particular action. This should trigger an error message in the client. |
| 404 | Not Found | A requested resource does not exist. |
| 405 | Method Not Allowed | Send automatically when an endpoint is accessed via a non-supported HTTP method. |
| 422 | Unprocessable Content | Send automatically when the automatic request body validation by `MapRequestPayload` fails because the request body was invalid. |
| 429 | Too Many Requests | Send automatically when the endpoints rate-limited is exceeded. |
| 500 | Internal Server Error | Send automatically when an unhandled error occurs in the backend. |

[Other status codes](https://restfulapi.net/http-status-codes/) can be used but should be well documented in that function. The REST endpoints should never return 5xx codes deliberately. Those codes are use to indicate server side errors, like syntax errors or database problems in the backend.

## HTTP Request and Response body
REST API requests and responses can carry content in their body. We use [JSON](https://www.json.org) as the data format. While other HTTP methods do, GET and DELETE don't support a request body.

The JSON objects should follow these conventions:
- Keys are in english and use lower *camelCase* (e.g. `regionId`, not `region_id` nor `RegionId`)
- Date and time values are represented in [ISO 8601](https://www.iso.org/iso-8601-date-and-time-format.html) format. Symfony handles this automatically when serializing DateTime objects to JSON.
- Boolean properties are prefixed `is` (e.g. `isPublic: true` instead of `public: true`)
- Property names should be specific and hint at the use of the field (e.g. `createdAt` instead of `time`,  `author` instead of `user`)
- Request and response types should follow the same structure.

::: tip EXAMPLE
```json
{
  "id": 12345,
  "name": "Bäckerei Brotler",
  "createdAt": "2024-05-14T19:30:00+02:00",
  "regionId": 678,
  "location": {
    "lat": 49.927162,
    "lon": 9.016253
  },
  "address": {
    "street": "Jina-Mahsa-Amini-Platz 7a",
    "zip": "13928",
    "city": "Berlin",
  },
  "isPublic": true
}
```
:::

## HTTP query parameters
Query parameters are parameters that can be added to the URI after the endpoint for further specification, like filtering or pagination.

::: tip EXAMPLE
- Filtering: GET `/map/markers/stores?status=cooperating&help=open&scope=region` (Returns only the markers for stores that are cooperating, looking for help and that are in the current users regions.)
- Pagination: GET `/bells?limit=50&offset=30` (Returns up to 50 bells, starting from the 31st)
:::
