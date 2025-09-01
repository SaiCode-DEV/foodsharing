<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Codeception\Example;
use Tests\Support\AcceptanceTester;

class StoreUserCest
{
    private $bezirk_id;
    private $storeCoordinator;
    private $storeCoordinator2;
    private $storeCoordinator3;
    private $foodsaver;
    private $foodsaver2;
    private $store;

    public function _before(AcceptanceTester $I): void
    {
        $this->bezirk_id = $I->createRegion('A region I test with', fillMailbox: false);
        $this->storeCoordinator = $I->createStoreCoordinator(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $this->store = $I->createStore($this->bezirk_id['id']);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);
        $this->storeCoordinator2 = $I->createStoreCoordinator(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator2['id'], true);
        $this->storeCoordinator3 = $I->createStoreCoordinator(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator3['id'], true);
        $this->foodsaver = $I->createFoodsaver(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $I->addStoreTeam($this->store['id'], $this->foodsaver['id']);
        $this->foodsaver2 = $I->createFoodsaver(null, ['bezirk_id' => $this->bezirk_id['id']]);
        $I->addStoreTeam($this->store['id'], $this->foodsaver2['id']);
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
        $I->login($this->storeCoordinator['email']);
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
        $I->login($this->storeCoordinator['email']);
        $this->store = $I->createStore($this->bezirk_id['id'], null, null, ['presse' => $example[0]]);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);

        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        $I->see('Namensnennung');
    }

    /**
     * Test that the store.chat.managers button opens the managers chat.
     */
    public function OpenManagersChatFromStorePageAsManager(AcceptanceTester $I): void
    {
        // Login as store coordinator
        $I->login($this->storeCoordinator['email']);
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        // Click the managers chat button (using the text-key or icon as selector)
        $I->click('[data-test="store-chat-managers"]');

        // Wait for chat box and see the remaining managers
        $I->waitForElementVisible('.chatboxtitle', 5);
        $I->see($this->storeCoordinator2['name'], '.chatboxtitle');
        $I->see($this->storeCoordinator3['name'], '.chatboxtitle');
    }

    /**
     * Test that the store.chat.managers button opens the managers chat.
     */
    public function OpenManagersChatFromStorePageAsMember(AcceptanceTester $I): void
    {
        // Login as foodsaver
        $I->login($this->foodsaver['email']);
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        // Click the managers chat button (using the text-key or icon as selector)
        $I->click('[data-test="store-chat-managers"]');

        // Wait for chat box and see all managers
        $I->waitForElementVisible('.chatboxtitle', 5);
        $I->see($this->storeCoordinator['name'], '.chatboxtitle');
        $I->see($this->storeCoordinator2['name'], '.chatboxtitle');
        $I->see($this->storeCoordinator3['name'], '.chatboxtitle');
    }
}
