<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;

class StoreUserCest
{
    public function _before(AcceptanceTester $I): void
    {
        $this->bezirk_id = $I->createRegion('A region I test with', fillMailbox: false);
        $this->storeCoordinator = $I->createStoreCoordinator(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $I->login($this->storeCoordinator['email']);
    }

    /**
     * @example[1, "1-3 kg"]
     * @example[2, "3-5 kg"]
     * @example[3, "5-10 kg"]
     * @example[4, "10-20 kg"]
     * @example[5, "20-30 kg"]
     * @example[6, "30-40 kg"]
     * @example[7, "40-50 kg"]
     * @example[8, "mehr als 50 kg"]
     */
    public function SeeTheFetchedQuantity(AcceptanceTester $I, Example $example): void
    {
        $this->store = $I->createStore($this->bezirk_id['id'], null, null, ['abholmenge' => $example[0]]);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);

        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->see('Abholmenge pro Person');
        $I->see($example[1]);
    }

    /**
     * @example[0, "private"]
     * @example[1, "public"]
     */
    public function SeeStoreMentioning(AcceptanceTester $I, Example $example): void
    {
        $this->store = $I->createStore($this->bezirk_id['id'], null, null, ['presse' => $example[0]]);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);

        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->see('Namensnennung');
    }
}
