<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class SettingsCest
{
    private $region;
    private $fspAdmin;
    private $foodsaver;

    final public function _before(AcceptanceTester $I): void
    {
        $this->foodsaver = $I->createFoodsaver();
        $this->fspAdmin = $I->createFoodsaver();
        $this->region = $I->createRegion(fillMailbox: false);
        $this->foodSharePoint = $I->createFoodSharePoint($this->fspAdmin['id'], $this->region['id']);
    }

    final public function canEditInternalSelfDescription(AcceptanceTester $I): void
    {
        $newSelfDesc = 'This is a new self description!';
        $I->login($this->foodsaver['email']);
        $I->amOnPage('/user/current/settings?sub=general');
        $I->waitForPageBody();
        $I->fillField('#about_me_intern', $newSelfDesc);
        $I->click('Speichern');
        $I->waitForActiveAPICalls();

        $I->amOnPage('/user/' . $this->foodsaver['id'] . '/profile');
        $I->waitForPageBody();
        $I->see($newSelfDesc);
    }
}
