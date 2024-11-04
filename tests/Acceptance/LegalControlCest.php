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
        $I->dontSee('Nimmst du die Vereinbarung zur Kenntnis?');
    }

    public function testGivenIAmLoggedInThenICanAcceptThePrivacyPolicy(AcceptanceTester $I): void
    {
        $I->checkOption('#legal_form_privacyPolicyAcknowledged');
        $I->click('Einstellungen übernehmen');
        $I->waitForActiveAPICalls();
        $I->seeCurrentUrlEquals('/legal');
    }

    public function testGivenIAmLoggedInAndIDontAcceptThePrivacyPolicyThenIAmStillAskedForConsent(AcceptanceTester $I): void
    {
        $I->uncheckOption('#legal_form_privacyPolicyAcknowledged');
        $I->click('Einstellungen übernehmen');
        $I->see('Akzeptierst du unsere Datenschutzerklärung?');
    }

    public function testGivenIAmLoggedInAndWantToDeleteMyAccountThenIGetRedirectedToTheDeleteAccountPage(AcceptanceTester $I): void
    {
        $this->isDeleted = true;
        $I->click('ich möchte meinen Account löschen.');
        $I->seeCurrentUrlEquals('/user/' . $this->user['id'] . '/settings?sub=deleteaccount');
    }

    public function testGivenIAmLoggedInAndHaveARoleHigherThanOneThenICanAcceptThePrivacyPolicyAndNotice(AcceptanceTester $I): void
    {
        $I->checkOption('#legal_form_privacyPolicyAcknowledged');
        $I->selectOption('#legal_form_privacyNoticeAcknowledged', 'Ich habe die Belehrung zur Kenntnis genommen.');
        $I->click('Einstellungen übernehmen');
        $I->seeCurrentUrlEquals('/legal');
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => Role::AMBASSADOR->value]);
    }

    public function testGivenIAmLoggedInAndAHaveRoleHigherThanOneThenICanDegradeToFoodsaver(AcceptanceTester $I): void
    {
        $I->checkOption('#legal_form_privacyPolicyAcknowledged');
        $I->selectOption('#legal_form_privacyNoticeAcknowledged', 'Ich akzeptiere die vorgenannten Grundsätze');
        $I->click('Einstellungen übernehmen');
        $I->seeInPopup('Bist du dir sicher?');
        $I->cancelPopup();
        $I->click('Einstellungen übernehmen');
        $I->seeInPopup('Bist du dir sicher?');
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => 3]);
        $I->acceptPopup();
        $I->seeCurrentUrlEquals('/legal');
        $I->seeInDatabase('fs_foodsaver', ['id' => $this->user['id'], 'rolle' => 1]);
    }
}
