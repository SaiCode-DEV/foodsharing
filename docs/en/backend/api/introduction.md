---
order: -1
---

# REST API
## Introduction
The [REST](https://en.wikipedia.org/wiki/REST) API is the primary communication interface between backend and frontends like [web clients](/deployment/requests#rest-api), user scripts or possibly native apps.

## API Documentations
The API documentation is generated automatically from code annotations. That way all available API endpoints are documented:
- [Here in the Devdocs](/api)
- [For Beta ](https://beta.foodsharing.de/api/doc/)
- [For Production ](https://foodsharing.de/api/doc/)
- [For the local development environment](http://localhost:18080/api/doc/)

The API is documented using [OpenAPI](https://swagger.io/docs/specification) and rendered by [swagger-php](https://swagger.io/). 

## When to provide data via API
Once the webpage is loaded, any client side requests to the server (without webpage reloads) must happen via the REST API.
This means that the API needs to be used every time, data is refreshed dynamically or changed by user interaction.

Currently some [Controllers](/backend/php/php-controllers) include the application data directly in the HTML response. We want to reduce this to a minimum, to allow for client side caching of the HTML responses in the future. Therefor even data that is required for fully displaying the page should rather be fetched via the API.

::: tip EXAMPLE
Consider the store page. Usage of the API is mandatory for
- Refreshing the displayed data like the pickup slots
- Loading further information like store log entries or more wall posts
- Editing data, like changing the store information
- Deleting data, like removing pickup slots or kicking out team members.

For many reasons you should also use the api for loading the store description, team and even name. The page should optimally work with initially being provided only the store id. 
:::
