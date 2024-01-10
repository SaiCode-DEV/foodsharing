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

```
./scripts/test
```

or 

```
./scripts/test Acceptance LoginCest
```

or

```
./scripts/test api SearchApiCest
```

or to run a specific test

```
./scripts/test api SearchApiCest:canOnlySearchWhenLoggedIn
```

To stop the Test containers run ```./scripts/stop test```.

So far, end to end testing is working nicely (called acceptance tests in codeception).
They run with a headless Chrome and Selenium inside the Docker setup, they are run on CI build too.

We are working on [restructing the code](https://gitlab.com/foodsharing-dev/foodsharing/issues/68) to enable unit testing.

The test contains stay around after running, and you can visit the test app
[in your browser](http://localhost:28080/), and it has
[it's own phpmyadmin](http://localhost:28081/).

If you want to run with debug mode turned on, then use: `./scripts/test --debug`.

## Writing unit tests

CodeCeption uses PHPUnitTests under the hood and therefore the [PHPUnit test documentation](https://phpunit.readthedocs.io/en/8.0/) can be helpful.

http://codeception.com/docs/modules/WebDriver#Actions is a very useful page, showing all the things can call on`$I`.
Please read the command descriptions carefully.

### How acceptance tests work

Tests are run through selenium on firefox.
The interaction of the test with the browser is defined by commands.
Keep in mind that this is on very high level.
Selenium does at most points not know what the browser is doing!

It is especially hard to get waits right as the blocking/waiting behaviour
of the commands may change with the test driver (PhantomJS, Firefox, Chromium, etc.).

```
$I->amOnPage
```
uses WebDriver GET command and waits for the HTML body of the page to be loaded (JavaScript onload handler fired),
but nothing else.

```
$I->click
```
just fires a click event on the given element. It does not wait for anything afterwards!
If you expect a page reload or any asynchronous requests happening, you need to wait for that before
being able to assert any content.

Even just a javascript popup, like an alert, may not be visible immediately!

```
$I->waitForPageBody()
```
can be used to wait for the static page load to be done.
It does also not wait for any javascript executed etc.

### HtmlAcceptanceTests

Acceptance tests using the `HtmlAcceptanceTester` class are run in *PhpBrowser*. Those tests run on a lower level then WebDriver. They can only test a page's HTML content. Therefore features like JavaScript are not available, but tests run faster.

From [Codeception documentation](https://codeception.com/docs/03-AcceptanceTests):

| | `HtmlAcceptanceTester` | `AcceptanceTester`
|-|------------------------|--------------------
|JavaScript | No | Yes
|`see`/`seeElement` checks if text is… | …present in the HTML source | …actually visible to the user
|Speed | Fast | Slow
