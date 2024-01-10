# Controller migration (Porting to Symfony)

## Prerequisites

- Routing:
    - old controllers are mostly called through these kinds of URLs: `/?page=general&sub=`
    - Symfony does not support that kind of routing.
      Because all our routes first go through the mandatory `page` parameter, we can however port this 1:1:
      For example, `/?page=settings&sub=any&id=get&c=parameters` would turn into `/settings?sub=any&id=get&c=parameters`.
      We can then add a Symfony route for `/settings` pointing to our ported controller,
      and keep doing whatever handling of query parameters as is.
    - Check in https://gitlab.com/foodsharing-dev/images/-/blob/master/web/foodsharing.conf -
      is there a rewrite related to the controller you want to refactor? Getting rid of those is a hard topic,
      because it involves synchronizing the MR with an MR in the images repository (for the dev environment),
      an MR in the ansible repository (for beta and production), and finally the deployment to beta and production.
      `@_fridtjof_` is working towards getting rid of those. If you want to get involved, ask him for details on slack.
      <!-- I did not bother writing generic documentation here,
        because it's the next thing I'll work on. Once they're all gone,
        there is no need for documenting them anymore.
        If a rewrite remains that turns out hard to port,
        I will write specific documentation about the pain points involved. -fridtjof -->
    
- The `sub` parameter
    - does the controller use the `sub` parameter?
    Telltale signs are: `$_GET['sub']`, `$request->query->get('sub')` and `$this->sub`.
    In the first iteration of the `FoodsharingController` compatibility layer, there is no support for porting this yet.
    - However, you do not really need to at first. We're just moving off page= for now.

## Porting
- Create a new Controller extending `FoodsharingController` in the same module as the old one
    - The name should be the same, except the suffix, which should be `Controller` instead of `Control`.
    This allows for keeping both the old and the new controller side by side.
    It also ensures Webpack does not break (Javascript files in the same Module are loaded based on the Controller name).

- Port the code from the old controller to the new one for each action.
    This is the most individual step, so only the most common patterns are documented here:

### Basic controller structure

```php
<?php

namespace Foodsharing\Modules\Basket;

use Foodsharing\Lib\FoodsharingController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExampleController extends FoodsharingController
{
	#[Route(path: "/example", name: "example_index"]
	public function index(Request $request): Response
	{
        // controller logic here
		return $this->renderGlobal();
	}
}
```
Notable differences:
- `FoodsharingController` is extended instead of `Control`.
    It is intended as a compatibility layer for some of the features `Control` has.
    However, if there is a better way to do something using Symfony features, use it!
- Routes are defined as annotations on the actions they lead to.
    If you worked on the REST API before, this should be quite familiar.
- Parameters are part of the route, which can also have validation for them.
- Parameters are direct arguments to the function, based on their name. Magic, isn't it?
- Any response to the request must be returned as a Response object.

### How do I port...?
Common code patterns and how to port them:

#### There is a constructor

Port: Usually, you should be able to carry over the constructor from the old controller as-is.
Most importantly, you need to call the parent constructor, though.
```php
public function __construct(/* DI arguments */)
{
    parent::__construct($container);

    /* ...*/
}
```

#### The controller (and/or its View class, if it has one) uses PageHelper everywhere and never calls `Control::render`

Explanation: PageHelper stores (mostly) HTML and other data for rendering.
IndexController uses it to render the final website after the controller finishes.

Port: You can replicate this by doing the following at the end of your controller action:
```php
return $this->renderGlobal();
```

#### The controller takes a $response argument, and renders into it

Explanation: Some controllers already use a Response object.

Port:
Create the response manually instead of taking it as a parameter, and explicitly return it.
```php
public function someAction($id): Response
{
    $response = new Response();
    // do stuff with it as usual
    return $response;
}
```

### Finishing up

- Change any URLs in other parts of the code to refer to the new Controller's URLs.
    If it's in a controller, generate the URL instead of hardcoding it: https://symfony.com/doc/current/routing.html#generating-urls.
    Generating URLs for other parts of the application (mainly JS) is not easily possible yet.

- If you just ported something based on the `page` parameter, you can throw out the old controller, and instead add the `page` value to `Routing::PORTED`.
  Any `page=xxx` URLs will then automatically be redirected towards the new controller.
  If you're changing any more routing beyond that (e.g. `sub`), please do the redirects in the new controller.

- Finally, update any tests that expected the old URLs
