<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Tests\Support\AcceptanceTester;

class LegalControlCest
{
    private $user;
    private $isDeleted;

    public function _before(AcceptanceTester $I): void
    {
        $this->isDeleted = false;
        $this->user = $I->createAmbassador();
        $I->login($this->user['email']);
        $I->amOnPage('/legal');
        $I->see('Datenschutzerklärung');
    }

    public function _after(AcceptanceTester $I): void
    {
        if (!$this->isDeleted) {
            $lastModified = $I->updateThePrivacyPolicyDate();
            $I->resetThePrivacyPolicyDate($lastModified);
            $I->logMeOut();
            $I->seeCurrentUrlEquals('/');
        }
    }

    public function testGivenIAmNotLoggedInThenTheLegalPageShowsThePrivacyPolicyWithoutAskingForConsent(AcceptanceTester $I): void
    {
        $I->logMeOut();
        $I->amOnPage('/?page=legal');
        $I->see('Datenschutzerklärung');
        $I->dontSee('Akzeptierst du unsere Datenschutzerklärung?');
    }

    public function testGivenIAmLoggedInThenICanAcceptThePrivacyPolicy(AcceptanceTester $I): void
    {
        $I->selectOption('select', 'Ja, ich akzeptiere die Datenschutzerklärung');
        $I->click('Akzeptieren');
        $I->waitForActiveAPICalls();
        $I->seeCurrentUrlEquals('/dashboard');
    }

    public function testGivenIAmLoggedInAndIDontAcceptThePrivacyPolicyThenIAmStillAskedForConsent(AcceptanceTester $I): void
    {
        $I->selectOption('select', 'Bitte auswählen');
        $I->dontSee('button', 'Akzeptieren');
        $I->see('Akzeptierst du unsere Datenschutzerklärung?');
    }

    public function testGivenIAmLoggedInAndWantToDeleteMyAccountThenIGetRedirectedToTheDeleteAccountPage(AcceptanceTester $I): void
    {
        $this->isDeleted = true;
        $I->selectOption('select', 'Nein, ich akzeptiere die Datenschutzerklärung nicht. Ich möchte meinen Account löschen.');
        $I->click('Meinen Account löschen');
        $I->seeCurrentUrlEquals('/user/current/deleteaccount');
    }

    public function testGivenIAmLoggedInAndHaveARoleHigherThanOneThenICanAcceptThePrivacyPolicyAndNotice(AcceptanceTester $I): void
    {
        $I->selectOption('select', 'Ja, ich akzeptiere die Datenschutzerklärung und die Datenschutzbelehrung.');
        $I->click('Akzeptieren');
        $I->waitForActiveAPICalls();
        $I->seeCurrentUrlEquals('/dashboard');
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => Role::AMBASSADOR->value]);
    }

    public function testGivenIAmLoggedInAndAHaveRoleHigherThanOneThenICanDegradeToFoodsaver(AcceptanceTester $I): void
    {
        $I->selectOption('select', 'Ich akzeptiere nur die Datenschutzerklärung und möchte zur:zum Foodsaver:in zurückgestuft werden.');
        $I->click('Zur:Zum Foodsaver:in zurückgestufen');
        $I->waitForElementVisible('#modalFoodsaverAccount', 10);
        $I->waitForElementVisible('#modalFoodsaverAccount .modal-content', 10);
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => 3]);
        $I->see('Achtung: Du hast ausgewählt, dass du zur:zum Foodsaver:in herabgestuft werden möchtest. Bist du dir sicher?', '#modalFoodsaverAccount .alert');
        $I->click('.modal-footer .btn-success');
        $I->waitForElementNotVisible('#modalFoodsaverAccount', 10);
        $I->click('Zur:Zum Foodsaver:in zurückgestufen');
        $I->waitForElementVisible('#modalFoodsaverAccount', 10);
        $I->click('.modal-footer .btn-danger');
        $I->waitForActiveAPICalls();
        $I->seeCurrentUrlEquals('/dashboard');
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => 1]);
    }
}
