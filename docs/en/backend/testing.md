# Testing

We use [Codeception](https://codeception.com/docs/01-Introduction#) for testing, especially testing the php code.

## Useful commands and common pitfalls

Useful commands for testing and common pitfalls.

| Command | Action | Pitfall |
|---|---|---|
| amOnPage | Changes URL, loads page, waits for body visible | Do not use to assert being on a URL |
| amOnSubdomain | Changes internal URL state | Does not load a page |
| amOnUrl | Changes internal URL state | Does not load a page |
| click | Fires JavaScript click event | Does not wait for anything to happen afterwards |
| seeCurrentUrlEquals | Checks on which URL the browser is (e.g. after a redirect) | |
| submitForm | Fills form details and submits it via click on the submit button | Does not wait for anything to happen afterwards |
| waitForElement | Waits until a specific element is available in the DOM | |
| waitForPageBody | Waits until the page body is visible (e.g. after click is expected to load a new page) | |

## Running tests

Run the tests with:

```bash
./scripts/test-backend
```

or

```bash
./scripts/test-backend Api SearchApiCest
```

or to run a specific test

```bash
./scripts/test-backend Api SearchApiCest:canOnlySearchWhenLoggedIn
```

If you want to run with debug mode turned on, then use

```bash
./scripts/test-backend --debug
```

To stop the Test containers run ```./scripts/stop test```.

We are working on [restructing the code](https://gitlab.com/foodsharing-dev/foodsharing/issues/68) to enable unit testing.

The test contains stay around after running, and you can visit the test app
[in your browser](http://localhost:28080/), and it has
[it's own phpmyadmin](http://localhost:28081/).

## Writing unit tests

CodeCeption uses PHPUnitTests under the hood and therefore the [PHPUnit test documentation](https://phpunit.readthedocs.io/en/8.0/) can be helpful.
