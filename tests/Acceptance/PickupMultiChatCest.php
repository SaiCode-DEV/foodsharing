<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\Support\AcceptanceTester;

/**
 * @group api-group-3
 */
class PickupMultiChatCest
{
    private $store;
    private $bezirkId;
    private $storeCoordinator;

    public function _before(AcceptanceTester $I): void
    {
        $this->bezirkId = $I->createRegion('A region for multi chat test', fillMailbox: false);
        $this->storeCoordinator = $I->createStoreCoordinator(null, ['bezirk_id' => $this->bezirkId['id']]);
        $I->login($this->storeCoordinator['email']);
    }

    /**
     * Test that the slot multi chat button is shown and works when there are
     * other users signed up for a pickup.
     */
    public function seeSlotMultiChatButton(AcceptanceTester $I): void
    {
        $this->store = $I->createStore($this->bezirkId['id']);
        $I->addStoreTeam($this->store['id'], $this->storeCoordinator['id'], true);
        $otherUser = $I->createFoodsaver();
        $I->addStoreTeam($this->store['id'], $otherUser['id'], false);

        // Create a pickup and sign up both users
        $date = date('Y-m-d H:i:s', strtotime('+1 day'));
        $I->addPickup($this->store['id'], ['time' => $date, 'fetchercount' => 3]);
        $I->addPicker($this->store['id'], $this->storeCoordinator['id'], ['date' => $date]);
        $I->addPicker($this->store['id'], $otherUser['id'], ['date' => $date]);

        // Load store page
        $I->amOnPage($I->storeUrl($this->store['id']));
        $I->waitForActiveAPICalls();

        // Go to the pickup section and check for the multi chat button
        $I->seeElement('[data-test="pickup-options-dropdown"]');
        $I->click('[data-test="pickup-options-dropdown"]');
        $I->seeElement('[data-test="slot-multi-chat"]');
        $I->click('[data-test="slot-multi-chat"]');

        // Check that the chat UI appears
        $I->waitForElementVisible('.chatboxtitle', 5);
        $I->see($otherUser['name'], '.chatboxtitle');
    }
}
