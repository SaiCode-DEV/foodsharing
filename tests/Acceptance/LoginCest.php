<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class LoginCest
{
    private $foodsaver;

    public function _before(AcceptanceTester $I): void
    {
        $this->pass = sq('pass');
        $this->foodsaver = $I->createFoodsharer($this->pass);
    }

    public function testLogin(AcceptanceTester $I): void
    {
        $I->wantTo('ensure you can login');
        $I->amOnPage('/');
        $I->executeJS('window.localStorage.clear();');
        $I->waitForElement('.testing-login-dropdown');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $this->foodsaver['email']);
        $I->fillField('#testing-login-input-password > input', $this->pass);
        $I->click('.testing-login-click-submit');
        $I->waitForActiveAPICalls();
        $I->waitForElementNotVisible('#pulse-success');
        $I->waitForPageBody();
        $I->waitForElement('.testing-intro-field');
        $I->see('Hallo ' . $this->foodsaver['name'], '.testing-intro-field');
        $I->seeCookieHasSessionExpiry('FS_SESSID');

        $I = $this;
    }

    public function testRememberLogin(AcceptanceTester $I): void
    {
        $I->amOnPage('/');
        $I->executeJS('window.localStorage.clear();');
        $I->waitForElement('.testing-login-dropdown');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $this->foodsaver['email']);
        $I->fillField('#testing-login-input-password > input', $this->pass);
        $I->dontSeeCheckboxIsChecked('.testing-login-input-remember');
        $I->click('.testing-login-input-remember');
        $I->seeCheckboxIsChecked('.testing-login-input-remember');
        $I->click('.testing-login-click-submit');
        $I->waitForActiveAPICalls();
        $I->waitForElementNotVisible('#pulse-success');
        $I->waitForPageBody();
        $I->waitForElement('.testing-intro-field');
        $I->see('Hallo ' . $this->foodsaver['name'], '.testing-intro-field');
        $I->seeCookieHasNoSessionExpiry('FS_SESSID');

        $I->amOnPage('/logout');

        $I->amOnPage('/');
        $I->click('.testing-login-dropdown');
        $I->fillField('.testing-login-input-email', $this->foodsaver['email']);
        $I->seeCheckboxIsChecked('.testing-login-input-remember');
    }
}
