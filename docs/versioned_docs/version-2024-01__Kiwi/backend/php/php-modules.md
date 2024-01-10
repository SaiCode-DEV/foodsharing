# Modules

A lot of code is sorted into modules in the `/src/Modules` directory.
This is a sorting by topic: each module contains files for one topic.
That can be a [gateway](./php-gateways),
a controller, an (old) view, javascript, css, (old) [XHR](../../deployment/requests#xhr),
(old) [models](#deprecated-module-structure).

The [Rest api controllers](../api/introduction) do not go into
their respective module directory but into the `/src/RestApi`
directory. This does not have a good reason but it's the way it is now. 

## Deprecated module structure

Since legacy code is still widespread through the repository it is important to understand it, too.

The (php) code is roughly structured with [Model - View - Controller](https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller).

The communication with the database is found in Model classes.
For example we can find `sql`-commands to manipulate a foodsaver in `/src/Modules/Foodsaver/FoodsaverModel.php`.
Those are executed with the functions inherited from the `Db` class (see `use Foodsharing\Lib\Db\Db;`, for example `$this->q(...)` where `q` stands for `query`.

## Newer module structure

Instead of Model classes, that hold both, data query logic and domain logic, we move towards splitting these up
into [Gateway classes](php-gateways) and [Transaction classes](php-transactions).

For a general description what „domain logic“ is, see section [Transactions](php-transactions).

Note that all of the following guidelines have a lot of exceptions
in the existing code. Nevertheless try to heed the following guidelines
in code you write and refactor.
