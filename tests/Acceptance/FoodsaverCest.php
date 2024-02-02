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
        $this->region = $I->createRegion();
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
        $I->amOnPage('/?page=foodsaver&a=edit&id=' . $fsId);
        $I->selectOption('Benutzer:innenrolle', 'Foodsaver:in');
        $I->click('Speichern');

        $I->amOnPage('/?page=foodsaver&a=edit&id=' . $fsId);
        $I->selectOption('Benutzer:innenrolle', 'Foodsharer:in');
        $I->click('Speichern');

        $I->dontSeeInDatabase('fs_foodsaver_has_bell', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_foodsaver_has_bezirk', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_botschafter', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_betrieb_team', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_abholer', ['foodsaver_id' => $fsId]);
        $I->dontSeeInDatabase('fs_foodsaver_has_conversation', ['foodsaver_id' => $fsId]);
        $I->seeNumRecords(7, 'fs_quiz_session', ['foodsaver_id' => $fsId, 'quiz_id' => Role::FOODSAVER->value, 'status' => SessionStatus::FAILED]);
        $I->seeInDatabase('fs_foodsaver', ['rolle' => Role::FOODSHARER->value, 'quiz_rolle' => Role::FOODSHARER->value]);
    }

    final public function canEditLocation(AcceptanceTester $I): void
    {
        $fsId = $this->foodsharer['id'];

        $address = 'Teststraße 1 37073 Teststadt Deutschland';
        $I->login($this->orga['email']);
        $I->amOnPage('/?page=foodsaver&a=edit&id=' . $fsId);
        $I->waitForPageBody();
        $I->fillField('#addresspicker', $address);
        $I->waitForElementVisible('#addresspicker_listbox');
        $I->click("//*[@id='addresspicker_listbox']//*[contains(text(), 'Teststraße 1')]");
        $I->click('Speichern');
        $I->waitForPageBody();

        $I->amOnPage('/?page=foodsaver&a=edit&id=' . $fsId);
        $I->waitForPageBody();
        $I->seeInField('#anschrift', 'Teststraße 1');
        $I->seeInField('#plz', '37073');
        $I->seeInField('#ort', 'Teststadt');
        $I->assertEqualsWithDelta($I->grabValueFrom('#lat'), 51.0, 0.001);
        $I->assertEqualsWithDelta($I->grabValueFrom('#lon'), 9.0, 0.001);
    }
}
