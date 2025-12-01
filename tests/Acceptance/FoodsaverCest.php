<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

class FoodsaverCest
{
    private $region;
    private $foodsharer;
    private $orga;

    public function _before(AcceptanceTester $I): void
    {
        $this->region = $I->createRegion(fillMailbox: false);
        $regionId = $this->region['id'];
        $this->foodsharer = $I->createFoodsharer();
        $I->addRegionMember($regionId, $this->foodsharer['id']);
        $this->orga = $I->createOrga();
        $I->addRegionAdmin($regionId, $this->orga['id']);
    }

    /*
     * TODO: As soon as Vuetify (or other autocomplete is done) this test should be reactivated.
     *
     * datalist are currently buggy in codeception. We need to wait for a fix (or a workaround).
     */
    /* final public function canEditLocation(AcceptanceTester $I): void
    {
        $fsId = $this->foodsharer['id'];

        $address = 'Teststra';
        $I->login($this->orga['email']);
        $I->amOnPage('/user/' . $fsId . '/settings');
        $I->waitForActiveAPICalls();

        // Find an address in the search field
        $I->click('#change-address-button');
        $I->waitForText('Adresse auswählen');
        $I->fillField('#search-address-input', $address);
        $I->waitForElementVisible('#suggestions option');
        $I->click("//*[contains(text(), 'Teststraße 1')]");
        $I->click('Adresse übernehmen');
        $I->click('Speichern');
        $I->waitForActiveAPICalls();

        // Codeception's click function doesn't work with this switch checkbox. We have to click it with javascript.
        $I->click('#change-address-button');
        $I->waitForText('Adresse auswählen');
        $I->executeJs('document.getElementById(\'different_location\').click()');
        $I->seeInField('#input-street', 'Teststraße 1');
        $I->seeInField('#input-postal', '37073');
        $I->seeInField('#input-city', 'Teststadt');
        $I->assertEqualsWithDelta($I->grabValueFrom('input[name="lat"]'), 51.0, 0.001);
        $I->assertEqualsWithDelta($I->grabValueFrom('input[name="lon"]'), 9.0, 0.001);
        $I->click('Adresse übernehmen');
        $I->click('Speichern');
        $I->waitForActiveAPICalls();
    } */
}
