<?php

declare(strict_types=1);

namespace Tests\Support;

use Codeception\Actor;
use Codeception\Lib\Friend;
use Tests\Support\_generated\AcceptanceTesterActions;

/**
 * Inherited Methods.
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method Friend haveFriend($name, $actorClass = NULL)
 *
 * @SuppressWarnings(PHPMD)
 */
class AcceptanceTester extends Actor
{
    use AcceptanceTesterActions;
    use \Codeception\Lib\Actor\Shared\Friend;

    /**
     * Wait to see the body element.
     */
    public function waitForPageBody()
    {
        return $this->waitForElement(['css' => 'body']);
    }

    public function login($email, $password = 'password', $totp_code = null, $expected_to_fail = false): void
    {
        $I = $this;
        $I->amOnPage('/');
        $I->executeJS('window.localStorage.clear();');
        $I->waitForElement('.testing-login-dropdown');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $email);
        $I->fillField('#testing-login-input-password > input', $password);
        if ($totp_code !== null) {
            // Make field visible
            $I->executeJS('document.getElementById("testing-login-input-totp").hidden = false;');
            $I->waitForElementVisible('#testing-login-input-totp > input');
            $I->fillField('#testing-login-input-totp > input', $totp_code);
        }
        $I->click('.testing-login-click-submit');
        $I->waitForActiveAPICalls();
        $I->waitForElementNotVisible('#pulse-success');
        $I->waitForPageBody();
        if ($expected_to_fail) {
            $I->waitForElement('.testing-login-dropdown');

            return;
        }
        $I->waitForElement('.testing-intro-field');
        $I->see('Hallo', '.testing-intro-field');
    }

    public function logMeOut(): void
    {
        $this->amOnPage('/logout');
        $this->waitForPageBody();
    }

    /**
     * Assert if a regexp is on the text content of the page.
     *
     * @param string regexp to check
     * @param string selector to check in, default 'html'
     */
    public function seeMatches(string $regexp, $selector = 'html'): void
    {
        $text = $this->grabTextFrom($selector);
        $this->assertRegExp($regexp, $text);
    }

    public function waitForActiveAPICalls($timeout = 60): void
    {
        $this->waitForJS('return !window.hasActiveRequests();', $timeout);
    }
}
