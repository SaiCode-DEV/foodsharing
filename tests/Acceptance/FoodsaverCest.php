<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
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

    public function downgradeFoodsharerPermanently(AcceptanceTester $I): void
    {
        $fsId = $this->foodsharer['id'];

        $I->login($this->orga['email']);
        $I->amOnPage('/user/' . $fsId . '/settings');
        $I->selectOption('#input-role', 'Foodsaver:in');
        $I->click('Speichern');
        $I->waitForActiveAPICalls();
        $I->seeInDatabase('fs_foodsaver', [
            'id' => $this->foodsharer['id'],
            'rolle' => Role::FOODSAVER->value,
        ]);

        $I->amOnPage('/user/' . $fsId . '/settings');
        $I->selectOption('#input-role', 'Foodsharer:in');
        $I->click('Speichern');
        $I->waitForActiveAPICalls();

        $I->dontSeeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_botschafter', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_betrieb_team', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_abholer', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_foodsaver_has_conversation', ['foodsaver_id' => $fsId]);
        $I->seeNumRecords(5, 'fs_quiz_session', ['foodsaver_id' => $fsId, 'quiz_id' => Role::FOODSAVER->value, 'status' => SessionStatus::FAILED->value]);
        $I->seeInDatabase('fs_foodsaver', ['rolle' => Role::FOODSHARER->value, 'quiz_rolle' => Role::FOODSHARER->value]);
    }

    final public function canEditLocation(AcceptanceTester $I): void
    {
        $fsId = $this->foodsharer['id'];

        $address = 'Teststraße 1 37073 Teststadt Deutschland';
        $I->login($this->orga['email']);
        $I->amOnPage('/user/' . $fsId . '/settings');
        $I->waitForActiveAPICalls();

        // Find an address in the search field
        $I->click('#change-address-button');
        $I->waitForText('Adresse auswählen');
        $I->fillField('#search-address-input', $address);
        $I->waitForElementVisible('#search-address-input_listbox');
        $I->click("//*[@id='search-address-input_listbox']//*[contains(text(), 'Teststraße 1')]");
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
    }
}
